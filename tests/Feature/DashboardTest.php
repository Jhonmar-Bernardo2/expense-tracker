<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_staff_dashboard_remains_department_scoped_and_hides_central_budget_sections(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $financialManagementDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialManagementDepartment)->create();
        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $category = $this->createExpenseCategory();

        $this->createAllocation($financialUser, $financialManagementDepartment, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1500,
        ]);
        $this->createBudget($financialUser, $financialManagementDepartment, $category, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Lunch',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'user_id' => $otherUser->id,
            'department_id' => $otherDepartment->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Ops lunch',
            'amount' => 700.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['department' => $otherDepartment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('department_scope.department_id', $department->id)
                ->where('department_scope.can_select_department', false)
                ->where('dashboard_view.mode', 'staff')
                ->where('dashboard_view.title', 'Department Dashboard')
                ->where('dashboard_view.primary_section.title', 'My request statuses')
                ->has('dashboard_view.primary_metrics', 4)
                ->has('dashboard_view.quick_actions', 2)
                ->where('totals.expenses', 250)
                ->where('current_month.expenses', 250)
                ->where('recent_transactions.0.department.id', $department->id)
                ->where('budgets', null)
            );
    }

    public function test_admin_dashboard_can_filter_transaction_scope_and_still_sees_central_allocation_summary(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $financialManagementDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialManagementDepartment)->create();
        $adminDepartment = Department::factory()->create(['name' => 'Executive']);
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $admin = User::factory()->admin()->for($adminDepartment)->create();
        $userA = User::factory()->for($departmentA)->create();
        $userB = User::factory()->for($departmentB)->create();
        $category = $this->createExpenseCategory();

        $allocation = $this->createAllocation($financialUser, $financialManagementDepartment, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1500,
        ]);
        $budget = $this->createBudget($financialUser, $financialManagementDepartment, $category, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ]);

        Transaction::query()->create([
            'user_id' => $userA->id,
            'department_id' => $departmentA->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Finance lunch',
            'amount' => 300.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'user_id' => $userB->id,
            'department_id' => $departmentB->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Ops lunch',
            'amount' => 700.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['department' => $departmentB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard_view.mode', 'admin')
                ->where('dashboard_view.title', 'Executive Financial Dashboard')
                ->where('dashboard_view.primary_section.title', 'Latest critical approvals')
                ->has('dashboard_view.primary_metrics', 4)
                ->has('dashboard_view.quick_actions', 3)
                ->where('department_scope.department_id', $departmentB->id)
                ->where('totals.expenses', 700)
                ->where('recent_transactions.0.department.id', $departmentB->id)
                ->where('budgets.scope_label', 'Central allocation')
                ->where('budgets.active_allocation.id', $allocation->id)
                ->where('budgets.current_month_summary.approved_allocation', 1500)
                ->where('budgets.current_month_summary.total_allocated', 1000)
                ->where('budgets.current_month_summary.total_unallocated', 500)
                ->where('budgets.current_month_summary.total_spent', 1000)
                ->where('budgets.current_month_summary.total_remaining', 500)
                ->where('budgets.current_month_statuses.0.id', $budget->id)
                ->where('budgets.current_month_statuses.0.amount_spent', 1000)
            );
    }

    public function test_financial_management_user_sees_central_allocation_summary_on_dashboard(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $financialManagementDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialManagementDepartment)->create();
        $operationsDepartment = Department::factory()->create(['name' => 'Operations']);
        $salesDepartment = Department::factory()->create(['name' => 'Sales']);
        $operationsUser = User::factory()->for($operationsDepartment)->create();
        $salesUser = User::factory()->for($salesDepartment)->create();
        $category = $this->createExpenseCategory();

        $allocation = $this->createAllocation($financialUser, $financialManagementDepartment, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1800,
        ]);
        $budget = $this->createBudget($financialUser, $financialManagementDepartment, $category, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1200,
        ]);

        Transaction::query()->create([
            'user_id' => $operationsUser->id,
            'department_id' => $operationsDepartment->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Operations lunch',
            'amount' => 400.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'user_id' => $salesUser->id,
            'department_id' => $salesDepartment->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Sales lunch',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        $this->actingAs($financialUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard_view.mode', 'financial_management')
                ->where('dashboard_view.title', 'Financial Management Operations')
                ->where('dashboard_view.primary_section.title', 'Transaction approval queue')
                ->where('dashboard_view.secondary_section.title', 'Recent department requests')
                ->has('dashboard_view.primary_metrics', 6)
                ->has('dashboard_view.quick_actions', 3)
                ->where('department_scope.department_id', $financialManagementDepartment->id)
                ->where('department_scope.can_select_department', false)
                ->where('totals.expenses', 0)
                ->where('budgets.scope_label', 'Central allocation')
                ->where('budgets.active_allocation.id', $allocation->id)
                ->where('budgets.current_month_summary.approved_allocation', 1800)
                ->where('budgets.current_month_summary.total_allocated', 1200)
                ->where('budgets.current_month_summary.total_unallocated', 600)
                ->where('budgets.current_month_summary.total_spent', 650)
                ->where('budgets.current_month_summary.total_remaining', 1150)
                ->where('budgets.current_month_statuses.0.id', $budget->id)
                ->where('budgets.current_month_statuses.0.amount_spent', 650)
            );
    }

    private function financialManagementDepartment(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }

    private function createExpenseCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);
    }

    private function createBudget(
        User $user,
        Department $department,
        Category $category,
        array $attributes = [],
    ): Budget {
        return Budget::query()->create(array_merge([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000.00,
        ], $attributes));
    }

    private function createAllocation(
        User $user,
        Department $department,
        array $attributes = [],
    ): BudgetAllocation {
        return BudgetAllocation::query()->create(array_merge([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1500.00,
        ], $attributes));
    }
}
