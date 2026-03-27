<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('app.reports.index'))->assertRedirect(route('login'));
    }

    public function test_staff_report_scope_is_locked_to_their_department_and_hides_budget_sections(): void
    {
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
            'title' => 'Groceries',
            'amount' => 325.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        Transaction::query()->create([
            'user_id' => $otherUser->id,
            'department_id' => $otherDepartment->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Ops groceries',
            'amount' => 900.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        $this->actingAs($user)
            ->get(route('app.reports.index', ['month' => 3, 'year' => 2026, 'department' => $otherDepartment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/Reports/Index')
                ->where('filters.department', $department->id)
                ->where('department_scope.department_id', $department->id)
                ->where('summary.monthly.expenses', 325)
                ->where('breakdowns.budget_vs_actual', [])
                ->where('budget_summary', null)
            );
    }

    public function test_admin_can_filter_reports_by_department_and_still_see_central_allocation_data(): void
    {
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
            'title' => 'Finance groceries',
            'amount' => 200.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        Transaction::query()->create([
            'user_id' => $userB->id,
            'department_id' => $departmentB->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Ops groceries',
            'amount' => 325.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        $this->actingAs($admin)
            ->get(route('app.reports.index', ['month' => 3, 'year' => 2026, 'department' => $departmentB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', $departmentB->id)
                ->where('department_scope.department_id', $departmentB->id)
                ->where('summary.monthly.expenses', 325)
                ->where('budget_summary.scope_label', 'Central allocation')
                ->where('budget_summary.active_allocation.id', $allocation->id)
                ->where('budget_summary.current_month_summary.approved_allocation', 1500)
                ->where('budget_summary.current_month_summary.total_allocated', 1000)
                ->where('budget_summary.current_month_summary.total_unallocated', 500)
                ->where('budget_summary.current_month_summary.total_spent', 525)
                ->where('budget_summary.current_month_summary.total_remaining', 975)
                ->where('breakdowns.budget_vs_actual.0.id', $budget->id)
                ->where('breakdowns.budget_vs_actual.0.amount_spent', 525)
            );
    }

    public function test_financial_management_user_sees_central_allocation_data_in_reports(): void
    {
        $financialManagementDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialManagementDepartment)->create();
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $userA = User::factory()->for($departmentA)->create();
        $userB = User::factory()->for($departmentB)->create();
        $category = $this->createExpenseCategory();

        $allocation = $this->createAllocation($financialUser, $financialManagementDepartment, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1800,
        ]);
        $budget = $this->createBudget($financialUser, $financialManagementDepartment, $category, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1500,
        ]);

        Transaction::query()->create([
            'user_id' => $userA->id,
            'department_id' => $departmentA->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Finance groceries',
            'amount' => 300.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        Transaction::query()->create([
            'user_id' => $userB->id,
            'department_id' => $departmentB->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Ops groceries',
            'amount' => 450.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        $this->actingAs($financialUser)
            ->get(route('app.reports.index', ['month' => 3, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', $financialManagementDepartment->id)
                ->where('department_scope.department_id', $financialManagementDepartment->id)
                ->where('summary.monthly.expenses', 0)
                ->where('budget_summary.scope_label', 'Central allocation')
                ->where('budget_summary.active_allocation.id', $allocation->id)
                ->where('budget_summary.current_month_summary.approved_allocation', 1800)
                ->where('budget_summary.current_month_summary.total_allocated', 1500)
                ->where('budget_summary.current_month_summary.total_unallocated', 300)
                ->where('budget_summary.current_month_summary.total_spent', 750)
                ->where('budget_summary.current_month_summary.total_remaining', 1050)
                ->where('breakdowns.budget_vs_actual.0.id', $budget->id)
                ->where('breakdowns.budget_vs_actual.0.amount_spent', 750)
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
