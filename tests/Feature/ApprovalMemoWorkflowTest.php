<?php

namespace Tests\Feature;

use App\Enums\ApprovalVoucherAttachmentKind;
use App\Models\ApprovalMemo;
use App\Models\ApprovalMemoAttachment;
use App\Models\ApprovalVoucher;
use App\Models\ApprovalVoucherAttachment;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ApprovalVoucherAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesApprovalMemos;
use Tests\TestCase;

class ApprovalMemoWorkflowTest extends TestCase
{
    use CreatesApprovalMemos;
    use RefreshDatabase;

    public function test_requester_can_create_edit_and_submit_approval_memo(): void
    {
        Notification::fake();

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);

        $this->actingAs($staff)
            ->post(route('approval-memos.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $department->id,
                'remarks' => 'Need memo for team lunch request.',
            ])
            ->assertRedirect();

        $approvalMemo = ApprovalMemo::query()->firstOrFail();

        $this->assertSame('draft', $approvalMemo->status->value);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approval_memo.created']);

        $this->actingAs($staff)
            ->post(route('approval-memos.update', $approvalMemo), [
                '_method' => 'put',
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $department->id,
                'remarks' => 'Updated memo request.',
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->post(route('approval-memos.submit', $approvalMemo))
            ->assertRedirect();

        $this->assertSame('pending_approval', $approvalMemo->fresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approval_memo.updated']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approval_memo.submitted']);
        Notification::assertSentTo($admin, ApprovalVoucherAlertNotification::class);

        $this->actingAs($staff)
            ->get(route('approval-memos.show', $approvalMemo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApprovalMemos/Show')
                ->where('approval_memo.status', 'pending_approval')
            );
    }

    public function test_admin_can_approve_without_uploading_a_final_file_and_approved_memo_exposes_print_view(): void
    {
        Notification::fake();

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $otherStaff = User::factory()->create();
        $approvalMemo = $this->createPendingMemo($staff, $department);

        $this->actingAs($admin)
            ->patch(route('approval-memos.approve', $approvalMemo), [
                'admin_remarks' => 'Ready for final request preparation.',
            ])
            ->assertRedirect();

        $approvalMemo->refresh();

        $this->assertSame('approved', $approvalMemo->status->value);
        $this->assertSame($admin->id, $approvalMemo->approved_by);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approval_memo.approved']);
        Notification::assertSentTo($staff, ApprovalVoucherAlertNotification::class);

        $this->actingAs($staff)
            ->get(route('approval-memos.show', $approvalMemo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_memo.download_url', route('approval-memos.download', $approvalMemo))
                ->where('approval_memo.print_url', route('approval-memos.print', [
                    'approvalMemo' => $approvalMemo,
                    'autoprint' => 1,
                ]))
                ->where('approval_memo.permissions.can_delete', false)
                ->where('approval_memo.permissions.can_print', true)
            );

        $this->actingAs($staff)
            ->get(route('approval-memos.download', $approvalMemo))
            ->assertOk()
            ->assertDownload("{$approvalMemo->memo_no}.pdf")
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($staff)
            ->get(route('approval-memos.print', $approvalMemo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApprovalMemos/Print')
                ->where('auto_print', false)
                ->where('approval_memo.memo_no', $approvalMemo->memo_no)
                ->where('approval_memo.approved_by_user.name', $admin->name)
            );

        $this->actingAs($staff)
            ->get(route('approval-memos.print', [
                'approvalMemo' => $approvalMemo,
                'autoprint' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApprovalMemos/Print')
                ->where('auto_print', true)
                ->where('approval_memo.memo_no', $approvalMemo->memo_no)
            );

        $this->actingAs($admin)
            ->get(route('approval-memos.print', $approvalMemo))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('approval-memos.download', $approvalMemo))
            ->assertOk()
            ->assertDownload("{$approvalMemo->memo_no}.pdf")
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($otherStaff)
            ->get(route('approval-memos.print', $approvalMemo))
            ->assertNotFound();

        $this->actingAs($otherStaff)
            ->get(route('approval-memos.download', $approvalMemo))
            ->assertNotFound();
    }

    public function test_requester_can_delete_own_draft_memo_and_cleanup_support_files(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $approvalMemo = $this->createDraftMemo($staff, $department);
        $attachmentPath = "approval-memos/{$approvalMemo->id}/draft-support.pdf";

        Storage::disk('local')->put($attachmentPath, 'draft support file');

        $attachment = ApprovalMemoAttachment::query()->create([
            'approval_memo_id' => $approvalMemo->id,
            'uploaded_by' => $staff->id,
            'kind' => 'request_support',
            'original_name' => 'draft-support.pdf',
            'disk' => 'local',
            'path' => $attachmentPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
        ]);

        $this->actingAs($staff)
            ->delete(route('approval-memos.destroy', $approvalMemo))
            ->assertRedirect(route('approval-memos.index'));

        $this->assertDatabaseMissing('approval_memos', ['id' => $approvalMemo->id]);
        $this->assertDatabaseMissing('approval_memo_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approval_memo.deleted']);
        Storage::disk('local')->assertMissing($attachmentPath);
    }

    public function test_requester_can_delete_own_rejected_memo(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $approvalMemo = $this->createRejectedMemo($staff, $department);

        $this->actingAs($staff)
            ->delete(route('approval-memos.destroy', $approvalMemo))
            ->assertRedirect(route('approval-memos.index'));

        $this->assertDatabaseMissing('approval_memos', ['id' => $approvalMemo->id]);
    }

    public function test_pending_and_approved_memos_cannot_be_deleted(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $pendingMemo = $this->createPendingMemo($staff, $department);
        $approvedMemo = $this->createApprovedMemo($staff, $department);

        $this->actingAs($staff)
            ->from(route('approval-memos.show', $pendingMemo))
            ->delete(route('approval-memos.destroy', $pendingMemo))
            ->assertRedirect(route('approval-memos.show', $pendingMemo))
            ->assertSessionHasErrors('approval_memo');

        $this->actingAs($staff)
            ->from(route('approval-memos.show', $approvedMemo))
            ->delete(route('approval-memos.destroy', $approvedMemo))
            ->assertRedirect(route('approval-memos.show', $approvedMemo))
            ->assertSessionHasErrors('approval_memo');

        $this->assertDatabaseHas('approval_memos', ['id' => $pendingMemo->id]);
        $this->assertDatabaseHas('approval_memos', ['id' => $approvedMemo->id]);
    }

    public function test_linked_rejected_memo_cannot_be_deleted(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = $this->createCategory();
        $approvalMemo = $this->createRejectedMemo($staff, $department);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), $this->transactionPayload($department, $category))
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();
        $approvalVoucher->forceFill(['approval_memo_id' => $approvalMemo->id])->save();

        $this->actingAs($staff)
            ->from(route('approval-memos.show', $approvalMemo))
            ->delete(route('approval-memos.destroy', $approvalMemo))
            ->assertRedirect(route('approval-memos.show', $approvalMemo))
            ->assertSessionHasErrors('approval_memo');

        $this->assertDatabaseHas('approval_memos', ['id' => $approvalMemo->id]);
    }

    public function test_rejecting_a_memo_requires_a_reason_and_notifies_the_requester(): void
    {
        Notification::fake();

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $approvalMemo = $this->createPendingMemo($staff, $department);

        $this->actingAs($admin)
            ->from(route('approval-memos.show', $approvalMemo))
            ->patch(route('approval-memos.reject', $approvalMemo), [])
            ->assertRedirect(route('approval-memos.show', $approvalMemo))
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)
            ->patch(route('approval-memos.reject', $approvalMemo), [
                'rejection_reason' => 'Please clarify the business purpose.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $approvalMemo->fresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approval_memo.rejected']);
        Notification::assertSentTo($staff, ApprovalVoucherAlertNotification::class);
    }

    public function test_non_approved_memos_do_not_expose_download_or_print_actions(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $approvalMemo = $this->createRejectedMemo($staff, $department);

        $this->actingAs($staff)
            ->get(route('approval-memos.show', $approvalMemo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_memo.download_url', null)
                ->where('approval_memo.print_url', null)
                ->where('approval_memo.permissions.can_delete', true)
                ->where('approval_memo.permissions.can_print', false)
            );
    }

    public function test_transaction_request_requires_uploaded_pdf_before_submission(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = $this->createCategory();

        $payload = $this->transactionPayload($department, $category);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), $payload)
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();
        $this->assertNull($approvalVoucher->approval_memo_id);

        $this->actingAs($staff)
            ->from(route('approval-vouchers.show', $approvalVoucher))
            ->post(route('approval-vouchers.submit', $approvalVoucher))
            ->assertRedirect(route('approval-vouchers.show', $approvalVoucher))
            ->assertSessionHasErrors('approval_memo_pdf');

        $this->actingAs($staff)
            ->post(route('approval-vouchers.update', $approvalVoucher), array_merge($payload, [
                '_method' => 'put',
            ]))
            ->assertRedirect();

        $this->actingAs($staff)
            ->post(route('approval-vouchers.update', $approvalVoucher), array_merge($payload, [
                '_method' => 'put',
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
            ]))
            ->assertRedirect();

        $approvalVoucher->refresh();

        $this->assertNull($approvalVoucher->approval_memo_id);
        $this->assertSame(
            ApprovalVoucherAttachmentKind::ApprovalMemoPdf->value,
            $approvalVoucher->approvalMemoPdfAttachment?->kind->value,
        );

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $approvalVoucher))
            ->assertRedirect();

        $this->assertSame('pending_approval', $approvalVoucher->fresh()->status->value);
    }

    public function test_budget_draft_can_switch_linked_memo_and_same_memo_cannot_be_reused_on_another_voucher(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $category = $this->createCategory(['name' => 'Office supplies']);
        $memoOne = $this->createApprovedMemo($staff, $department, [
            'module' => 'budget',
            'action' => 'create',
        ]);
        $memoTwo = $this->createApprovedMemo($staff, $department, [
            'module' => 'budget',
            'action' => 'create',
            'memo_no' => 'AM-2026-09999',
        ]);

        $payload = $this->budgetPayload($department, $category);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), array_merge($payload, [
                'approval_memo_id' => $memoOne->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload('memo-one.pdf'),
            ]))
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();
        $originalMemoPdf = $approvalVoucher->fresh('approvalMemoPdfAttachment')->approvalMemoPdfAttachment;

        $this->assertNotNull($originalMemoPdf);
        Storage::disk('local')->assertExists($originalMemoPdf->path);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.update', $approvalVoucher), array_merge($payload, [
                '_method' => 'put',
                'approval_memo_id' => $memoTwo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload('memo-two.pdf'),
            ]))
            ->assertRedirect();

        $approvalVoucher->refresh();
        $updatedMemoPdf = $approvalVoucher->fresh('approvalMemoPdfAttachment')->approvalMemoPdfAttachment;

        $this->assertSame($memoTwo->id, $approvalVoucher->approval_memo_id);
        $this->assertNull($memoOne->fresh()->linkedApprovalVoucher);
        $this->assertSame($approvalVoucher->id, $memoTwo->fresh()->linkedApprovalVoucher?->id);
        $this->assertNotNull($updatedMemoPdf);
        $this->assertSame('memo-two.pdf', $updatedMemoPdf->original_name);
        Storage::disk('local')->assertMissing($originalMemoPdf->path);
        Storage::disk('local')->assertExists($updatedMemoPdf->path);

        $this->actingAs($staff)
            ->from(route('budgets.index'))
            ->post(route('approval-vouchers.store'), array_merge($payload, [
                'approval_memo_id' => $memoTwo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload('duplicate.pdf'),
            ]))
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('approval_memo_id');
    }

    public function test_rejected_voucher_can_resubmit_with_the_same_linked_memo_and_saved_pdf(): void
    {
        Storage::fake('local');

        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $category = $this->createCategory();
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
        $approvalMemo = $this->createApprovedMemo($staff, $department, [
            'module' => 'transaction',
            'action' => 'update',
        ]);

        $payload = array_merge($this->transactionPayload($department, $category), [
            'action' => 'update',
            'target_id' => $transaction->id,
            'title' => 'Taxi reimbursement',
            'amount' => 300,
            'approval_memo_id' => $approvalMemo->id,
            'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
        ]);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), $payload)
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $approvalVoucher))
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.reject', $approvalVoucher), [
                'rejection_reason' => 'Please revise the amount.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $approvalVoucher->fresh()->status->value);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $approvalVoucher))
            ->assertRedirect();

        $approvalVoucher->refresh();

        $this->assertSame('pending_approval', $approvalVoucher->status->value);
        $this->assertSame($approvalMemo->id, $approvalVoucher->approval_memo_id);
        $this->assertNotNull($approvalVoucher->approvalMemoPdfAttachment);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPendingMemo(User $requester, Department $department, array $overrides = []): ApprovalMemo
    {
        return ApprovalMemo::query()->create(array_merge([
            'memo_no' => sprintf('AM-2026-%05d', ApprovalMemo::query()->count() + 1),
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'approved_by' => null,
            'module' => 'transaction',
            'action' => 'create',
            'status' => 'pending_approval',
            'remarks' => 'Pending memo review.',
            'admin_remarks' => null,
            'rejection_reason' => null,
            'submitted_at' => '2026-03-24 09:00:00',
            'approved_at' => null,
            'rejected_at' => null,
            'created_at' => '2026-03-24 08:30:00',
            'updated_at' => '2026-03-24 09:00:00',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftMemo(User $requester, Department $department, array $overrides = []): ApprovalMemo
    {
        return ApprovalMemo::query()->create(array_merge([
            'memo_no' => sprintf('AM-2026-%05d', ApprovalMemo::query()->count() + 1),
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'approved_by' => null,
            'module' => 'transaction',
            'action' => 'create',
            'status' => 'draft',
            'remarks' => 'Draft memo request.',
            'admin_remarks' => null,
            'rejection_reason' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'created_at' => '2026-03-24 08:30:00',
            'updated_at' => '2026-03-24 08:30:00',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRejectedMemo(User $requester, Department $department, array $overrides = []): ApprovalMemo
    {
        return ApprovalMemo::query()->create(array_merge([
            'memo_no' => sprintf('AM-2026-%05d', ApprovalMemo::query()->count() + 1),
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'approved_by' => null,
            'module' => 'transaction',
            'action' => 'create',
            'status' => 'rejected',
            'remarks' => 'Rejected memo request.',
            'admin_remarks' => null,
            'rejection_reason' => 'Please revise the details.',
            'submitted_at' => '2026-03-24 09:00:00',
            'approved_at' => null,
            'rejected_at' => '2026-03-24 10:00:00',
            'created_at' => '2026-03-24 08:30:00',
            'updated_at' => '2026-03-24 10:00:00',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCategory(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Meals',
            'type' => 'expense',
        ], $overrides));
    }

    private function transactionPayload(Department $department, Category $category): array
    {
        return [
            'module' => 'transaction',
            'action' => 'create',
            'department_id' => $department->id,
            'type' => 'expense',
            'category_id' => $category->id,
            'title' => 'Team lunch',
            'amount' => 450,
            'description' => 'Monthly team lunch',
            'transaction_date' => '2026-03-24',
        ];
    }

    private function budgetPayload(Department $department, Category $category): array
    {
        return [
            'module' => 'budget',
            'action' => 'create',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1200,
        ];
    }
}
