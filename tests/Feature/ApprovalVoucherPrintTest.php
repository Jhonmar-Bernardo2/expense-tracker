<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApprovalVoucherPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_open_print_view_for_their_approval_voucher(): void
    {
        [$voucher, $requester] = $this->makeVoucher([
            'status' => 'approved',
            'approved_at' => '2026-03-24 10:45:00',
            'applied_at' => '2026-03-24 10:50:00',
            'remarks' => 'Approved by finance.',
        ]);

        $this->actingAs($requester)
            ->get(route('app.approval-vouchers.print', $voucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/ApprovalVouchers/Print')
                ->where('approval_voucher.id', $voucher->id)
                ->where('approval_voucher.status', 'approved')
                ->where('approval_voucher.remarks', 'Approved by finance.')
                ->where('approval_voucher.requested_by_user.name', $requester->name)
                ->where('approval_voucher.approved_by_user.name', 'Finance Admin')
                ->has('categories', 1)
                ->has('departments', 1)
            );
    }

    public function test_other_staff_cannot_open_someone_elses_print_view(): void
    {
        [$voucher] = $this->makeVoucher();
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $otherStaff = User::factory()->create(['department_id' => $otherDepartment->id]);

        $this->actingAs($otherStaff)
            ->get(route('app.approval-vouchers.print', $voucher))
            ->assertNotFound();
    }

    public function test_print_view_preserves_create_and_delete_payload_shapes(): void
    {
        [$createVoucher, $requester] = $this->makeVoucher([
            'action' => 'create',
            'before_payload' => null,
            'after_payload' => [
                'type' => 'expense',
                'title' => 'Printer toner',
                'amount' => 1800,
                'description' => 'For admin office',
                'transaction_date' => '2026-03-24',
            ],
        ]);

        [$deleteVoucher, $deleteRequester] = $this->makeVoucher([
            'action' => 'delete',
            'before_payload' => [
                'type' => 'expense',
                'title' => 'Old reimbursement',
                'amount' => 300,
                'description' => null,
                'transaction_date' => '2026-03-20',
            ],
            'after_payload' => null,
        ]);

        $this->actingAs($requester)
            ->get(route('app.approval-vouchers.print', $createVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_voucher.before_payload', null)
                ->where('approval_voucher.after_payload.title', 'Printer toner')
            );

        $this->actingAs($deleteRequester)
            ->get(route('app.approval-vouchers.print', $deleteVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_voucher.before_payload.title', 'Old reimbursement')
                ->where('approval_voucher.after_payload', null)
            );
    }

    public function test_print_view_includes_rejection_details_when_present(): void
    {
        [$voucher, $requester] = $this->makeVoucher([
            'status' => 'rejected',
            'rejection_reason' => 'Missing supporting receipt.',
            'rejected_at' => '2026-03-24 11:30:00',
        ]);

        $this->actingAs($requester)
            ->get(route('app.approval-vouchers.print', $voucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_voucher.status', 'rejected')
                ->where('approval_voucher.rejection_reason', 'Missing supporting receipt.')
                ->where('approval_voucher.approved_by_user.name', 'Finance Admin')
            );
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
