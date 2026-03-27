<?php

namespace Tests\Feature;

use App\Enums\ApprovalVoucherAttachmentKind;
use App\Models\ApprovalVoucher;
use App\Models\ApprovalVoucherAttachment;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApprovalVoucherAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_request_can_be_created_with_supporting_documents(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Meals',
            'type' => 'expense',
        ]);
        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $department->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Team lunch',
                'amount' => 450,
                'description' => 'Monthly lunch',
                'transaction_date' => '2026-03-24',
                'remarks' => 'Includes official receipt.',
                'attachments' => [
                    UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
                ],
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();
        $supportingAttachment = ApprovalVoucherAttachment::query()
            ->where('approval_voucher_id', $approvalVoucher->id)
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->sole();

        $this->assertSame('pending_approval', $approvalVoucher->status->value);
        $this->assertSame($approvalVoucher->id, $supportingAttachment->approval_voucher_id);
        $this->assertSame($staff->id, $supportingAttachment->uploaded_by);
        $this->assertSame('receipt.pdf', $supportingAttachment->original_name);
        Storage::disk('local')->assertExists($supportingAttachment->path);
    }

    public function test_allocation_request_can_be_created_with_supporting_documents(): void
    {
        Storage::fake('local');

        $department = $this->financialManagementDepartment();
        $staff = User::factory()->create(['department_id' => $department->id]);
        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 120000,
                'remarks' => 'Vendor quote attached.',
                'attachments' => [
                    UploadedFile::fake()->create('quote.png', 200, 'image/png'),
                ],
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $attachment = ApprovalVoucherAttachment::query()
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->sole();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame($department->id, $approvalVoucher->department_id);
        $this->assertSame('allocation', $approvalVoucher->module->value);
        $this->assertSame('quote.png', $attachment->original_name);
        $this->assertSame('image/png', $attachment->mime_type);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_delete_request_can_be_created_with_attachments_and_remarks(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Transportation',
            'type' => 'expense',
        ]);
        $transaction = Transaction::query()->create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Taxi',
            'amount' => 250,
            'description' => null,
            'transaction_date' => '2026-03-24',
        ]);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'delete',
                'target_id' => $transaction->id,
                'department_id' => $department->id,
                'remarks' => 'Duplicate charge request.',
                'attachments' => [
                    UploadedFile::fake()->create('memo.webp', 64, 'image/webp'),
                ],
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();
        $attachment = ApprovalVoucherAttachment::query()->firstOrFail();

        $this->assertSame('delete', $approvalVoucher->action->value);
        $this->assertSame('Duplicate charge request.', $approvalVoucher->remarks);
        $this->assertSame($approvalVoucher->id, $attachment->approval_voucher_id);
        $this->assertSame(ApprovalVoucherAttachmentKind::SupportingDocument->value, $attachment->kind->value);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_rejected_voucher_update_can_add_and_remove_attachments(): void
    {
        Storage::fake('local');

        [$approvalVoucher, $requester, $oldAttachment] = $this->makeVoucherWithAttachment([
            'status' => 'rejected',
            'remarks' => 'Original attachment attached.',
        ]);

        $this->actingAs($requester)
            ->post(route('approval-vouchers.update', $approvalVoucher), [
                '_method' => 'put',
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $approvalVoucher->department_id,
                'type' => 'expense',
                'category_id' => $approvalVoucher->after_payload['category_id'],
                'title' => 'Office chair replacement',
                'amount' => 3300,
                'description' => 'Updated request',
                'transaction_date' => '2026-03-25',
                'remarks' => 'Updated proof attached.',
                'remove_attachment_ids' => [$oldAttachment->id],
                'attachments' => [
                    UploadedFile::fake()->create('updated-receipt.pdf', 150, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $newAttachment = ApprovalVoucherAttachment::query()
            ->where('approval_voucher_id', $approvalVoucher->id)
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->sole();

        $this->assertDatabaseMissing('approval_voucher_attachments', [
            'id' => $oldAttachment->id,
        ]);
        Storage::disk('local')->assertMissing($oldAttachment->path);
        $this->assertSame('updated-receipt.pdf', $newAttachment->original_name);
        Storage::disk('local')->assertExists($newAttachment->path);
    }

    public function test_pending_voucher_cannot_mutate_attachments_through_update(): void
    {
        Storage::fake('local');

        [$approvalVoucher, $requester, $existingAttachment] = $this->makeVoucherWithAttachment([
            'status' => 'pending_approval',
            'submitted_at' => '2026-03-24 09:30:00',
        ]);

        $this->actingAs($requester)
            ->from(route('approval-vouchers.show', $approvalVoucher))
            ->post(route('approval-vouchers.update', $approvalVoucher), [
                '_method' => 'put',
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $approvalVoucher->department_id,
                'type' => 'expense',
                'category_id' => $approvalVoucher->after_payload['category_id'],
                'title' => 'Office chair replacement',
                'amount' => 3300,
                'description' => 'Updated request',
                'transaction_date' => '2026-03-25',
                'remarks' => 'Trying to edit pending request.',
                'remove_attachment_ids' => [$existingAttachment->id],
                'attachments' => [
                    UploadedFile::fake()->create('should-not-save.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('approval-vouchers.show', $approvalVoucher))
            ->assertSessionHasErrors('approval_voucher');

        $this->assertSame(2, ApprovalVoucherAttachment::query()->count());
        Storage::disk('local')->assertExists($existingAttachment->path);
        Storage::disk('local')->assertMissing(
            "approval-vouchers/{$approvalVoucher->id}/should-not-save.pdf",
        );
    }

    public function test_requester_and_admin_can_download_attachments_but_unrelated_staff_cannot(): void
    {
        Storage::fake('local');

        [$approvalVoucher, $requester, $attachment] = $this->makeVoucherWithAttachment();
        $admin = User::factory()->admin()->create();
        $otherStaff = User::factory()->create();

        $this->actingAs($requester)
            ->get(route('approval-vouchers.attachments.download', [
                'approvalVoucher' => $approvalVoucher,
                'attachment' => $attachment,
            ]))
            ->assertOk()
            ->assertDownload('receipt.pdf');

        $this->actingAs($admin)
            ->get(route('approval-vouchers.attachments.download', [
                'approvalVoucher' => $approvalVoucher,
                'attachment' => $attachment,
            ]))
            ->assertOk()
            ->assertDownload('receipt.pdf');

        $this->actingAs($otherStaff)
            ->get(route('approval-vouchers.attachments.download', [
                'approvalVoucher' => $approvalVoucher,
                'attachment' => $attachment,
            ]))
            ->assertNotFound();
    }

    public function test_detail_and_print_views_include_attachment_metadata_for_authorized_viewers(): void
    {
        Storage::fake('local');

        [$approvalVoucher, $requester, $attachment] = $this->makeVoucherWithAttachment();

        $downloadUrl = route('approval-vouchers.attachments.download', [
            'approvalVoucher' => $approvalVoucher,
            'attachment' => $attachment,
        ]);

        $this->actingAs($requester)
            ->get(route('approval-vouchers.show', $approvalVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApprovalVouchers/Show')
                ->where('approval_voucher.attachments.0.name', 'receipt.pdf')
                ->where('approval_voucher.attachments.0.size_bytes', 512)
                ->where('approval_voucher.attachments.0.download_url', $downloadUrl)
            );

        $this->actingAs($requester)
            ->get(route('approval-vouchers.print', $approvalVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApprovalVouchers/Print')
                ->where('approval_voucher.attachments.0.name', 'receipt.pdf')
                ->where('approval_voucher.attachments.0.mime_type', 'application/pdf')
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: ApprovalVoucher, 1: User, 2: ApprovalVoucherAttachment}
     */
    private function makeVoucherWithAttachment(array $overrides = []): array
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $requester = User::factory()->create([
            'name' => 'Request Staff',
            'department_id' => $department->id,
        ]);
        $approver = User::factory()->admin()->create([
            'name' => 'Finance Admin',
            'department_id' => $department->id,
        ]);
        $category = Category::query()->create([
            'name' => 'Office supplies',
            'type' => 'expense',
        ]);

        $approvalVoucher = ApprovalVoucher::query()->create(array_merge([
            'voucher_no' => 'AV-2026-00001',
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'module' => 'transaction',
            'action' => 'create',
            'status' => 'draft',
            'target_id' => null,
            'before_payload' => null,
            'after_payload' => [
                'department_id' => $department->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'title' => 'Office chair replacement',
                'amount' => 3200,
                'description' => 'Updated request',
                'transaction_date' => '2026-03-24',
            ],
            'remarks' => 'Please review.',
            'rejection_reason' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'applied_at' => null,
            'created_at' => '2026-03-24 09:00:00',
            'updated_at' => '2026-03-24 09:30:00',
        ], $overrides));

        $path = "approval-vouchers/{$approvalVoucher->id}/supporting-documents/receipt.pdf";

        Storage::disk('local')->put($path, 'fake-pdf-content');

        $attachment = ApprovalVoucherAttachment::query()->create([
            'approval_voucher_id' => $approvalVoucher->id,
            'uploaded_by' => $requester->id,
            'kind' => ApprovalVoucherAttachmentKind::SupportingDocument->value,
            'original_name' => 'receipt.pdf',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'created_at' => '2026-03-24 09:05:00',
            'updated_at' => '2026-03-24 09:05:00',
        ]);

        return [$approvalVoucher, $requester, $attachment];
    }

    private function financialManagementDepartment(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }
}
