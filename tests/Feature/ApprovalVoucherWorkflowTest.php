<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApprovalVoucherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_create_request_only_posts_after_financial_management_approval(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requestDepartment = Department::factory()->create(['name' => 'Operations']);
        $staff = User::factory()->for($requestDepartment)->create();
        $financialApprover = User::factory()->for($financialDepartment)->create();
        $admin = User::factory()->admin()->create();
        $category = $this->createExpenseCategory('Food');

        $this->actingAs($staff)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $requestDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Team lunch',
                'amount' => 450,
                'description' => 'Monthly team lunch',
                'transaction_date' => '2026-03-24',
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->assertSame('draft', $approvalVoucher->status->value);
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($staff)
            ->post(route('app.approval-vouchers.submit', $approvalVoucher), [])
            ->assertRedirect();

        $this->assertSame('pending_approval', $approvalVoucher->fresh()->status->value);

        $this->actingAs($admin)
            ->from(route('app.approval-vouchers.show', $approvalVoucher))
            ->patch(route('app.approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Admin should not approve transactions.',
            ])
            ->assertRedirect(route('app.approval-vouchers.show', $approvalVoucher))
            ->assertSessionHasErrors('approval_voucher');

        $this->actingAs($financialApprover)
            ->patch(route('app.approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Approved by Financial Management.',
            ])
            ->assertRedirect();

        $approvalVoucher->refresh();

        $this->assertSame('approved', $approvalVoucher->status->value);
        $this->assertDatabaseHas('transactions', [
            'origin_approval_voucher_id' => $approvalVoucher->id,
            'department_id' => $requestDepartment->id,
            'category_id' => $category->id,
            'title' => 'Team lunch',
            'type' => 'expense',
        ]);
    }

    public function test_transaction_update_and_delete_requests_only_apply_after_financial_management_approval(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requestDepartment = Department::factory()->create(['name' => 'Operations']);
        $staff = User::factory()->for($requestDepartment)->create();
        $financialApprover = User::factory()->for($financialDepartment)->create();
        $category = $this->createExpenseCategory('Transportation');
        $transaction = Transaction::query()->create([
            'user_id' => $staff->id,
            'department_id' => $requestDepartment->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Taxi',
            'amount' => 250,
            'description' => null,
            'transaction_date' => '2026-03-24',
        ]);

        $this->actingAs($staff)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'update',
                'target_id' => $transaction->id,
                'department_id' => $requestDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Taxi reimbursement',
                'amount' => 300,
                'description' => 'Updated amount',
                'transaction_date' => '2026-03-24',
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $updateVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame('Taxi', $transaction->fresh()->title);

        $this->actingAs($financialApprover)
            ->patch(route('app.approval-vouchers.approve', $updateVoucher), [
                'remarks' => 'Approved update',
            ])
            ->assertRedirect();

        $transaction->refresh();

        $this->assertSame('Taxi reimbursement', $transaction->title);
        $this->assertSame('300.00', (string) $transaction->amount);

        $this->actingAs($staff)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'delete',
                'target_id' => $transaction->id,
                'department_id' => $requestDepartment->id,
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $deleteVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->actingAs($financialApprover)
            ->patch(route('app.approval-vouchers.approve', $deleteVoucher), [
                'remarks' => 'Approved delete',
            ])
            ->assertRedirect();

        $transaction->refresh();

        $this->assertNotNull($transaction->voided_at);
        $this->assertSame($deleteVoucher->id, $transaction->voided_by_approval_voucher_id);

        $this->actingAs($staff)
            ->get(route('app.transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('transactions.data', []));
    }

    public function test_financial_management_can_submit_monthly_allocation_request_and_only_admin_can_approve_it(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requester = User::factory()->for($financialDepartment)->create();
        $adminDepartment = Department::factory()->create(['name' => 'Executive']);
        $admin = User::factory()->admin()->for($adminDepartment)->create();
        $financialCollaborator = User::factory()->for($financialDepartment)->create();

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 120000,
            ])
            ->assertRedirect();

        $allocationVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->assertDatabaseCount('budget_allocations', 0);

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.submit', $allocationVoucher), [])
            ->assertRedirect();

        $this->actingAs($financialCollaborator)
            ->from(route('app.approval-vouchers.show', $allocationVoucher))
            ->patch(route('app.approval-vouchers.approve', $allocationVoucher), [
                'remarks' => 'Financial Management should not approve allocations.',
            ])
            ->assertRedirect(route('app.approval-vouchers.show', $allocationVoucher))
            ->assertSessionHasErrors('approval_voucher');

        $this->actingAs($admin)
            ->patch(route('app.approval-vouchers.approve', $allocationVoucher), [
                'remarks' => 'Approved monthly allocation.',
            ])
            ->assertRedirect();

        $allocation = BudgetAllocation::query()->firstOrFail();

        $this->assertSame($allocationVoucher->id, $allocation->origin_approval_voucher_id);
        $this->assertSame($financialDepartment->id, $allocation->department_id);
        $this->assertSame('120000.00', (string) $allocation->amount_limit);
    }

    public function test_financial_management_collaborators_can_view_and_edit_allocation_vouchers_but_cannot_approve_them(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requester = User::factory()->for($financialDepartment)->create();
        $collaborator = User::factory()->for($financialDepartment)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 140000,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->actingAs($collaborator)
            ->get(route('app.approval-vouchers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_vouchers.meta.total', 1)
                ->where('approval_vouchers.links.next', null)
                ->where('approval_vouchers.data.0.id', $approvalVoucher->id)
                ->where('approval_vouchers.data.0.module', 'allocation')
                ->where('approval_vouchers.data.0.department.id', $financialDepartment->id)
            );

        $this->actingAs($collaborator)
            ->get(route('app.approval-vouchers.show', $approvalVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_voucher.permissions.can_edit', true)
                ->where('approval_voucher.permissions.can_submit', true)
                ->where('approval_voucher.permissions.can_approve', false)
            );

        $this->actingAs($collaborator)
            ->put(route('app.approval-vouchers.update', $approvalVoucher), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 175000,
                'remarks' => 'Updated allocation draft.',
            ])
            ->assertRedirect();

        $approvalVoucher->refresh();

        $this->assertSame(175000.0, $approvalVoucher->after_payload['amount_limit']);

        $this->actingAs($collaborator)
            ->post(route('app.approval-vouchers.submit', $approvalVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('app.approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Approved allocation draft.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('budget_allocations', [
            'origin_approval_voucher_id' => $approvalVoucher->id,
            'department_id' => $financialDepartment->id,
            'amount_limit' => 175000,
        ]);
    }

    public function test_pending_legacy_monthly_budget_removal_requests_cannot_be_approved(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requester = User::factory()->for($financialDepartment)->create();
        $admin = User::factory()->admin()->create();
        $allocation = BudgetAllocation::query()->create([
            'user_id' => $requester->id,
            'department_id' => $financialDepartment->id,
            'month' => 4,
            'year' => 2026,
            'amount_limit' => 140000,
        ]);
        $approvalVoucher = ApprovalVoucher::query()->create([
            'voucher_no' => 'AV-2026-99998',
            'department_id' => $financialDepartment->id,
            'requested_by' => $requester->id,
            'module' => 'allocation',
            'action' => 'delete',
            'status' => 'pending_approval',
            'target_id' => $allocation->id,
            'before_payload' => [
                'department_id' => $financialDepartment->id,
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 140000,
            ],
            'after_payload' => null,
            'submitted_at' => '2026-03-24 09:30:00',
        ]);

        $this->actingAs($admin)
            ->from(route('app.approval-vouchers.show', $approvalVoucher))
            ->patch(route('app.approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Legacy allocation removal should stay blocked.',
            ])
            ->assertRedirect(route('app.approval-vouchers.show', $approvalVoucher))
            ->assertSessionHasErrors([
                'approval_voucher' => 'Monthly budget removal requests are no longer supported.',
            ]);

        $this->assertNull($allocation->fresh()->archived_at);
        $this->assertSame('pending_approval', $approvalVoucher->fresh()->status->value);
    }

    public function test_financial_management_can_view_and_approve_department_transaction_requests_while_admin_is_read_only(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialApprover = User::factory()->for($financialDepartment)->create();
        $admin = User::factory()->admin()->create();
        $requestDepartment = Department::factory()->create(['name' => 'IT']);
        $requester = User::factory()->for($requestDepartment)->create();
        $category = $this->createExpenseCategory('Utilities');

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $requestDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Internet bill',
                'amount' => 3200,
                'description' => 'Monthly payment',
                'transaction_date' => '2026-03-25',
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->actingAs($financialApprover)
            ->get(route('app.approval-vouchers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('department_scope.can_select_department', true)
                ->where('approval_vouchers.meta.total', 1)
                ->where('approval_vouchers.links.prev', null)
                ->where('approval_vouchers.data.0.id', $approvalVoucher->id)
                ->where('approval_vouchers.data.0.department.id', $requestDepartment->id)
            );

        $this->actingAs($admin)
            ->get(route('app.approval-vouchers.show', $approvalVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_voucher.permissions.can_approve', false)
                ->where('approval_voucher.permissions.can_reject', false)
            );

        $this->actingAs($financialApprover)
            ->patch(route('app.approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Approved for release.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'origin_approval_voucher_id' => $approvalVoucher->id,
            'department_id' => $requestDepartment->id,
            'title' => 'Internet bill',
        ]);
    }

    public function test_financial_management_transaction_requests_apply_immediately_without_secondary_approval(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requester = User::factory()->for($financialDepartment)->create();
        $requestDepartment = Department::factory()->create(['name' => 'Operations']);
        $category = $this->createExpenseCategory('Finance operations');

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $requestDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Treasury release',
                'amount' => 1250,
                'description' => 'Finance desk expense',
                'transaction_date' => '2026-03-25',
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $createVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();
        $transaction = Transaction::query()->sole();

        $this->assertSame('approved', $createVoucher->status->value);
        $this->assertSame($transaction->id, $createVoucher->target_id);
        $this->assertSame($requestDepartment->id, $transaction->department_id);

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'update',
                'target_id' => $transaction->id,
                'department_id' => $requestDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Treasury release updated',
                'amount' => 1500,
                'description' => 'Finance desk expense updated',
                'transaction_date' => '2026-03-26',
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $updateVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame('approved', $updateVoucher->status->value);
        $this->assertSame('Treasury release updated', $transaction->fresh()->title);
        $this->assertSame('1500.00', (string) $transaction->fresh()->amount);

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'delete',
                'target_id' => $transaction->id,
                'department_id' => $requestDepartment->id,
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $deleteVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $transaction->refresh();

        $this->assertSame('approved', $deleteVoucher->status->value);
        $this->assertNotNull($transaction->voided_at);
        $this->assertSame($deleteVoucher->id, $transaction->voided_by_approval_voucher_id);
    }

    public function test_submitting_a_financial_management_transaction_draft_applies_it_immediately(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $requester = User::factory()->for($financialDepartment)->create();
        $requestDepartment = Department::factory()->create(['name' => 'IT']);
        $category = $this->createExpenseCategory('Internet');

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $requestDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Internet bill',
                'amount' => 3200,
                'description' => 'Monthly payment',
                'transaction_date' => '2026-03-25',
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame('draft', $approvalVoucher->status->value);
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($requester)
            ->post(route('app.approval-vouchers.submit', $approvalVoucher), [])
            ->assertRedirect();

        $approvalVoucher->refresh();

        $transaction = Transaction::query()->sole();

        $this->assertSame('approved', $approvalVoucher->status->value);
        $this->assertSame($transaction->id, $approvalVoucher->target_id);
        $this->assertSame($requestDepartment->id, $transaction->department_id);
    }

    public function test_legacy_budget_vouchers_remain_viewable_but_new_budget_vouchers_cannot_be_created_from_active_flow(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $category = $this->createExpenseCategory('Legacy budget');

        $this->actingAs($financialUser)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'create',
                'category_id' => $category->id,
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 5000,
            ])
            ->assertForbidden();

        $approvalVoucher = ApprovalVoucher::query()->create([
            'voucher_no' => 'AV-2026-99999',
            'department_id' => $financialDepartment->id,
            'requested_by' => $financialUser->id,
            'module' => 'budget',
            'action' => 'create',
            'status' => 'approved',
            'target_id' => null,
            'before_payload' => null,
            'after_payload' => [
                'department_id' => $financialDepartment->id,
                'category_id' => $category->id,
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 5000,
            ],
        ]);

        $this->actingAs($financialUser)
            ->get(route('app.approval-vouchers.show', $approvalVoucher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('approval_voucher.id', $approvalVoucher->id)
                ->where('approval_voucher.module', 'budget')
            );
    }

    private function financialManagementDepartment(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }

    private function createExpenseCategory(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'type' => 'expense',
        ]);
    }
}
