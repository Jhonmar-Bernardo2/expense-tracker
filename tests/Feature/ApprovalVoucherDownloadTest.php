<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalVoucherDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_download_pdf_for_their_approval_voucher(): void
    {
        [$voucher, $requester] = $this->makeVoucher([
            'status' => 'approved',
            'approved_at' => '2026-03-24 10:45:00',
            'applied_at' => '2026-03-24 10:50:00',
            'remarks' => 'Approved by finance.',
        ]);

        $response = $this->actingAs($requester)
            ->get(route('app.approval-vouchers.download', $voucher));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            "{$voucher->voucher_no}.pdf",
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_admin_can_download_pdf_for_visible_approval_voucher(): void
    {
        [$voucher, , $approver] = $this->makeVoucher();

        $response = $this->actingAs($approver)
            ->get(route('app.approval-vouchers.download', $voucher));

        $response->assertOk();
        $this->assertStringContainsString(
            "{$voucher->voucher_no}.pdf",
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_other_staff_cannot_download_someone_elses_pdf(): void
    {
        [$voucher] = $this->makeVoucher();
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $otherStaff = User::factory()->create(['department_id' => $otherDepartment->id]);

        $this->actingAs($otherStaff)
            ->get(route('app.approval-vouchers.download', $voucher))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: ApprovalVoucher, 1: User, 2: User}
     */
    private function makeVoucher(array $overrides = []): array
    {
        $sequence = ApprovalVoucher::query()->count() + 1;

        $department = Department::factory()->create(['name' => "Finance {$sequence}"]);
        $requester = User::factory()->create([
            'name' => 'Request Staff',
            'department_id' => $department->id,
        ]);
        $approver = User::factory()->admin()->create([
            'name' => 'Finance Admin',
            'department_id' => $department->id,
        ]);
        $category = Category::query()->create([
            'name' => "Office supplies {$sequence}",
            'type' => 'expense',
        ]);

        $beforePayload = [
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Office chair',
            'amount' => 2500,
            'description' => 'Existing request',
            'transaction_date' => '2026-03-20',
        ];

        $afterPayload = [
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Office chair replacement',
            'amount' => 3200,
            'description' => 'Updated request',
            'transaction_date' => '2026-03-24',
        ];

        $voucher = ApprovalVoucher::query()->create(array_merge([
            'voucher_no' => sprintf('AV-2026-%05d', $sequence),
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'module' => 'transaction',
            'action' => 'update',
            'status' => 'pending_approval',
            'target_id' => 77,
            'before_payload' => array_key_exists('before_payload', $overrides)
                ? (is_array($overrides['before_payload'])
                    ? array_merge($beforePayload, $overrides['before_payload'])
                    : $overrides['before_payload'])
                : $beforePayload,
            'after_payload' => array_key_exists('after_payload', $overrides)
                ? (is_array($overrides['after_payload'])
                    ? array_merge($afterPayload, $overrides['after_payload'])
                    : $overrides['after_payload'])
                : $afterPayload,
            'remarks' => 'Please review.',
            'rejection_reason' => null,
            'submitted_at' => '2026-03-24 09:30:00',
            'approved_at' => null,
            'rejected_at' => null,
            'applied_at' => null,
            'created_at' => '2026-03-24 09:00:00',
            'updated_at' => '2026-03-24 09:30:00',
        ], collect($overrides)->except(['before_payload', 'after_payload'])->all()));

        return [$voucher, $requester, $approver];
    }
}
