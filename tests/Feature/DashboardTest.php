<?php

namespace Tests\Feature;

use App\Models\Budget;
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

    public function test_staff_dashboard_is_forced_to_their_department_scope(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

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
                ->where('budgets.current_month_summary.total_budgeted', 1000)
                ->where('budgets.current_month_summary.total_spent', 250)
                ->where('budgets.current_month_statuses.0.id', $budget->id)
            );
    }

    public function test_admin_dashboard_defaults_to_all_departments_and_can_filter_to_one_department(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $admin = User::factory()->admin()->create();
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $category = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $userA = User::factory()->for($departmentA)->create();
        $userB = User::factory()->for($departmentB)->create();

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
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('department_scope.is_all_departments', true)
                ->where('totals.expenses', 1000)
            );

        $this->actingAs($admin)
            ->get(route('dashboard', ['department' => $departmentB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('department_scope.department_id', $departmentB->id)
                ->where('totals.expenses', 700)
                ->where('recent_transactions.0.department.id', $departmentB->id)
            );
    }
}
