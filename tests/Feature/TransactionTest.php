<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $department = Department::factory()->create();
        $category = $this->createCategory();

        $this->get(route('app.transactions.index'))->assertRedirect(route('login'));

        $this->post(route('app.approval-vouchers.store'), [
            'module' => 'transaction',
            'action' => 'create',
            'department_id' => $department->id,
            'type' => 'expense',
            'category_id' => $category->id,
            'title' => 'Lunch',
            'amount' => 150,
            'description' => null,
            'transaction_date' => '2026-03-15',
        ])->assertRedirect(route('login'));
    }

    public function test_staff_user_only_views_transactions_from_their_department(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $expenseCategory = $this->createCategory(['name' => 'Food', 'type' => 'expense']);
        $incomeCategory = $this->createCategory(['name' => 'Salary', 'type' => 'income']);

        $expenseTransaction = $this->createTransaction($user, $department, $expenseCategory, [
            'type' => 'expense',
            'title' => 'Groceries',
            'transaction_date' => '2026-03-10',
        ]);

        $this->createTransaction($otherUser, $otherDepartment, $incomeCategory, [
            'type' => 'income',
            'title' => 'Payroll',
            'transaction_date' => '2026-03-12',
        ]);

        $this->actingAs($user)
            ->get(route('app.transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/Transactions/Index')
                ->where('filters.department', $department->id)
                ->where('department_scope.department_id', $department->id)
                ->where('transactions.meta.total', 1)
                ->where('transactions.meta.last_page', 1)
                ->where('transactions.links.next', null)
                ->where('transactions.data.0.id', $expenseTransaction->id)
                ->where('transactions.data.0.department.id', $department->id)
                ->missing('transactions.data.1')
            );
    }

    public function test_staff_cannot_force_transaction_request_into_another_department(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->for($department)->create();
        $category = $this->createCategory(['name' => 'Food']);

        $this->actingAs($user)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $otherDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Lunch',
                'amount' => 175.50,
                'description' => 'Team meal',
                'transaction_date' => '2026-03-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_vouchers', [
            'requested_by' => $user->id,
            'department_id' => $department->id,
            'module' => 'transaction',
            'action' => 'create',
            'status' => 'draft',
        ]);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_admin_can_filter_any_department_but_cannot_create_transaction_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $staffA = User::factory()->for($departmentA)->create();
        $staffB = User::factory()->for($departmentB)->create();
        $category = $this->createCategory(['name' => 'Food']);

        $this->createTransaction($staffA, $departmentA, $category, ['title' => 'Finance lunch']);
        $transactionB = $this->createTransaction($staffB, $departmentB, $category, ['title' => 'Ops lunch']);

        $this->actingAs($admin)
            ->get(route('app.transactions.index', ['department' => $departmentB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', $departmentB->id)
                ->where('department_scope.department_id', $departmentB->id)
                ->where('transactions.meta.total', 1)
                ->where('transactions.links.prev', null)
                ->where('transactions.data.0.id', $transactionB->id)
                ->missing('transactions.data.1')
            );

        $this->actingAs($admin)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $departmentB->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Department supplies',
                'amount' => 600,
                'description' => null,
                'transaction_date' => '2026-04-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('approval_vouchers', 0);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_financial_management_user_can_view_all_transactions_and_filter_by_department(): void
    {
        $financialDepartment = Department::factory()->create([
            'name' => 'Financial Management',
            'is_financial_management' => true,
        ]);
        $operationsDepartment = Department::factory()->create(['name' => 'Operations']);
        $salesDepartment = Department::factory()->create(['name' => 'Sales']);
        $financialUser = User::factory()->for($financialDepartment)->create();
        $operationsUser = User::factory()->for($operationsDepartment)->create();
        $salesUser = User::factory()->for($salesDepartment)->create();
        $category = $this->createCategory(['name' => 'Food']);

        $operationsTransaction = $this->createTransaction($operationsUser, $operationsDepartment, $category, [
            'title' => 'Operations lunch',
            'transaction_date' => '2026-03-10',
        ]);
        $salesTransaction = $this->createTransaction($salesUser, $salesDepartment, $category, [
            'title' => 'Sales dinner',
            'transaction_date' => '2026-03-12',
        ]);

        $this->actingAs($financialUser)
            ->get(route('app.transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/Transactions/Index')
                ->where('filters.department', null)
                ->where('department_scope.department_id', null)
                ->where('department_scope.can_select_department', true)
                ->where('department_scope.is_all_departments', true)
                ->where('transactions.meta.total', 2)
                ->where('transactions.data.0.id', $salesTransaction->id)
                ->where('transactions.data.1.id', $operationsTransaction->id)
            );

        $this->actingAs($financialUser)
            ->get(route('app.transactions.index', ['department' => $operationsDepartment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', $operationsDepartment->id)
                ->where('department_scope.department_id', $operationsDepartment->id)
                ->where('department_scope.selected_department.id', $operationsDepartment->id)
                ->where('transactions.meta.total', 1)
                ->where('transactions.data.0.id', $operationsTransaction->id)
                ->missing('transactions.data.1')
            );
    }

    public function test_financial_management_transaction_create_requests_apply_immediately(): void
    {
        $financialDepartment = Department::factory()->create([
            'name' => 'Financial Management',
            'is_financial_management' => true,
        ]);
        $operationsDepartment = Department::factory()->create(['name' => 'Operations']);
        $financialUser = User::factory()->for($financialDepartment)->create();
        $category = $this->createCategory(['name' => 'Supplies']);

        $this->actingAs($financialUser)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'create',
                'department_id' => $operationsDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Cross-department purchase',
                'amount' => 600,
                'description' => 'Immediate financial posting',
                'transaction_date' => '2026-04-01',
                'auto_submit' => true,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();
        $transaction = Transaction::query()->sole();

        $this->assertSame('approved', $approvalVoucher->status->value);
        $this->assertSame($transaction->id, $approvalVoucher->target_id);
        $this->assertSame($operationsDepartment->id, $transaction->department_id);
        $this->assertSame($financialUser->id, $transaction->user_id);
        $this->assertSame($approvalVoucher->id, $transaction->origin_approval_voucher_id);
    }

    public function test_staff_cannot_request_updates_for_another_departments_transaction(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $category = $this->createCategory();
        $transaction = $this->createTransaction($otherUser, $otherDepartment, $category);

        $this->actingAs($user)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'transaction',
                'action' => 'update',
                'target_id' => $transaction->id,
                'department_id' => $department->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Updated',
                'amount' => 100,
                'description' => null,
                'transaction_date' => '2026-03-20',
            ])
            ->assertNotFound();
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Utilities',
            'type' => 'expense',
        ], $attributes));
    }

    private function createTransaction(
        ?User $user = null,
        ?Department $department = null,
        ?Category $category = null,
        array $attributes = [],
    ): Transaction {
        $user ??= User::factory()->create();
        $department ??= $user->department;
        $category ??= $this->createCategory();

        return Transaction::query()->create(array_merge([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => $category->type->value,
            'title' => 'Utilities',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ], $attributes));
    }
}
