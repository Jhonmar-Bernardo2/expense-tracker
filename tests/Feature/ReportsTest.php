<?php

namespace Tests\Feature;

use App\Models\Budget;
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
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_staff_report_scope_is_locked_to_their_department(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $category = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $budget = Budget::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000.00,
        ]);

        Budget::query()->create([
            'user_id' => $otherUser->id,
            'department_id' => $otherDepartment->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 500.00,
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
            ->get(route('reports.index', ['month' => 3, 'year' => 2026, 'department' => $otherDepartment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('filters.department', $department->id)
                ->where('department_scope.department_id', $department->id)
                ->where('summary.monthly.expenses', 325)
                ->where('breakdowns.budget_vs_actual.0.id', $budget->id)
                ->where('breakdowns.budget_vs_actual.0.department.id', $department->id)
            );
    }

    public function test_admin_can_filter_reports_by_department(): void
    {
        $admin = User::factory()->admin()->create();
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $category = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $userA = User::factory()->for($departmentA)->create();
        $userB = User::factory()->for($departmentB)->create();

        Budget::query()->create([
            'user_id' => $userA->id,
            'department_id' => $departmentA->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000.00,
        ]);

        $budgetB = Budget::query()->create([
            'user_id' => $userB->id,
            'department_id' => $departmentB->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 600.00,
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
            ->get(route('reports.index', ['month' => 3, 'year' => 2026, 'department' => $departmentB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', $departmentB->id)
                ->where('department_scope.department_id', $departmentB->id)
                ->where('summary.monthly.expenses', 325)
                ->where('breakdowns.budget_vs_actual.0.id', $budgetB->id)
                ->where('breakdowns.budget_vs_actual.0.department.id', $departmentB->id)
            );
    }
}
