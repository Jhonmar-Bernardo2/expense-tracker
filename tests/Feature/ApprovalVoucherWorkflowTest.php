<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Concerns\CreatesApprovalMemos;

class ApprovalVoucherWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApprovalMemos;

    public function test_transaction_create_request_only_posts_after_admin_approval(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);
        $approvalMemo = $this->createApprovedMemo($staff, $department, [
            'module' => 'transaction',
            'action' => 'create',
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
                'description' => 'Monthly team lunch',
                'transaction_date' => '2026-03-24',
                'approval_memo_id' => $approvalMemo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->assertSame('draft', $approvalVoucher->status->value);
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $approvalVoucher), [])
            ->assertRedirect();

        $this->assertSame('pending_approval', $approvalVoucher->fresh()->status->value);

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Approved',
            ])
            ->assertRedirect();

        $approvalVoucher->refresh();

        $this->assertSame('approved', $approvalVoucher->status->value);
        $this->assertDatabaseHas('transactions', [
            'origin_approval_voucher_id' => $approvalVoucher->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'title' => 'Team lunch',
            'type' => 'expense',
        ]);
    }

    public function test_transaction_update_and_delete_requests_only_apply_after_approval_and_voided_rows_are_hidden(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
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
        $updateMemo = $this->createApprovedMemo($staff, $department, [
            'module' => 'transaction',
            'action' => 'update',
        ]);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'update',
                'target_id' => $transaction->id,
                'department_id' => $department->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Taxi reimbursement',
                'amount' => 300,
                'description' => 'Updated amount',
                'transaction_date' => '2026-03-24',
                'approval_memo_id' => $updateMemo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
            ])
            ->assertRedirect();

        $updateVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame('Taxi', $transaction->fresh()->title);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $updateVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $updateVoucher), [
                'remarks' => 'Approved update',
            ])
            ->assertRedirect();

        $transaction->refresh();

        $this->assertSame('Taxi reimbursement', $transaction->title);
        $this->assertSame('300.00', (string) $transaction->amount);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'delete',
                'target_id' => $transaction->id,
                'department_id' => $department->id,
            ])
            ->assertRedirect();

        $deleteVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $deleteVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $deleteVoucher), [
                'remarks' => 'Approved delete',
            ])
            ->assertRedirect();

        $transaction->refresh();

        $this->assertNotNull($transaction->voided_at);
        $this->assertSame($deleteVoucher->id, $transaction->voided_by_approval_voucher_id);

        $this->actingAs($staff)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('transactions.data', []));

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.expenses', 0)
                ->where('current_month.expenses', 0)
            );

        $this->actingAs($staff)
            ->get(route('reports.index', ['month' => 3, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.monthly.expenses', 0)
                ->where('breakdowns.expenses_by_category', [])
            );
    }

    public function test_budget_requests_only_apply_after_approval_and_archived_rows_are_hidden(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Office supplies',
            'type' => 'expense',
        ]);
        $createMemo = $this->createApprovedMemo($staff, $department, [
            'module' => 'budget',
            'action' => 'create',
        ]);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'create',
                'department_id' => $department->id,
                'category_id' => $category->id,
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 1200,
                'approval_memo_id' => $createMemo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
            ])
            ->assertRedirect();

        $createVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->assertDatabaseCount('budgets', 0);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $createVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $createVoucher), [
                'remarks' => 'Approved create',
            ])
            ->assertRedirect();

        $budget = Budget::query()->firstOrFail();
        $updateMemo = $this->createApprovedMemo($staff, $department, [
            'module' => 'budget',
            'action' => 'update',
        ]);

        $this->assertSame($createVoucher->id, $budget->origin_approval_voucher_id);
        $this->assertSame('1200.00', (string) $budget->amount_limit);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'update',
                'target_id' => $budget->id,
                'department_id' => $department->id,
                'category_id' => $category->id,
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 1500,
                'approval_memo_id' => $updateMemo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
            ])
            ->assertRedirect();

        $updateVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame('1200.00', (string) $budget->fresh()->amount_limit);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $updateVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $updateVoucher), [
                'remarks' => 'Approved update',
            ])
            ->assertRedirect();

        $budget->refresh();

        $this->assertSame('1500.00', (string) $budget->amount_limit);

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'delete',
                'target_id' => $budget->id,
                'department_id' => $department->id,
            ])
            ->assertRedirect();

        $deleteVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('approval-vouchers.submit', $deleteVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $deleteVoucher), [
                'remarks' => 'Approved delete',
            ])
            ->assertRedirect();

        $budget->refresh();

        $this->assertNotNull($budget->archived_at);
        $this->assertSame($deleteVoucher->id, $budget->archived_by_approval_voucher_id);

        $this->actingAs($staff)
            ->get(route('budgets.index', ['month' => 3, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('budgets', []));

        $this->actingAs($staff)
            ->get(route('reports.index', ['month' => 3, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('breakdowns.budget_vs_actual', []));
    }

    public function test_admin_can_self_approve_their_own_request(): void
    {
        $department = Department::factory()->create();
        $admin = User::factory()->admin()->create(['department_id' => $department->id]);
        $category = Category::query()->create([
            'name' => 'Miscellaneous income',
            'type' => 'income',
        ]);
        $approvalMemo = $this->createApprovedMemo($admin, $department, [
            'module' => 'transaction',
            'action' => 'create',
        ]);

        $this->actingAs($admin)
            ->post(route('approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $department->id,
                'type' => 'income',
                'category_id' => $category->id,
                'title' => 'Adjustment',
                'amount' => 500,
                'description' => null,
                'transaction_date' => '2026-03-24',
                'approval_memo_id' => $approvalMemo->id,
                'approval_memo_pdf' => $this->makeApprovalMemoPdfUpload(),
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('approval-vouchers.submit', $approvalVoucher), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('approval-vouchers.approve', $approvalVoucher), [
                'remarks' => 'Self-approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'origin_approval_voucher_id' => $approvalVoucher->id,
            'title' => 'Adjustment',
            'type' => 'income',
        ]);
    }
}
