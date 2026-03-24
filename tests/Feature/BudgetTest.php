<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $department = Department::factory()->create();
        $category = $this->createCategory();

        $this->get(route('budgets.index'))->assertRedirect(route('login'));

        $this->post(route('approval-vouchers.store'), [
            'module' => 'budget',
            'action' => 'create',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ])->assertRedirect(route('login'));
    }

    public function test_staff_user_views_only_their_departments_budgets_and_usage(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $expenseCategory = $this->createCategory(['name' => 'Food', 'type' => 'expense']);
        $this->createCategory(['name' => 'Salary', 'type' => 'income']);

        $budget = $this->createBudget($user, $department, $expenseCategory, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ]);

        $this->createBudget($otherUser, $otherDepartment, $expenseCategory, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 800,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'title' => 'Groceries',
            'amount' => 400.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'user_id' => $otherUser->id,
            'department_id' => $otherDepartment->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'title' => 'Other team lunch',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budgets/Index')
                ->where('filters.department', $department->id)
                ->where('department_scope.department_id', $department->id)
                ->where('department_scope.can_select_department', false)
                ->has('budgets', 1)
                ->has('categories', 1)
                ->where('budgets.0.id', $budget->id)
                ->where('budgets.0.amount_spent', 400)
                ->where('budgets.0.department.id', $department->id)
                ->missing('budgets.1')
            );
    }

    public function test_staff_cannot_force_budget_request_into_another_department(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->for($department)->create();
        $category = $this->createCategory();

        $this->actingAs($user)
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'create',
                'department_id' => $otherDepartment->id,
                'category_id' => $category->id,
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 2500,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_vouchers', [
            'requested_by' => $user->id,
            'department_id' => $department->id,
            'module' => 'budget',
            'action' => 'create',
            'status' => 'draft',
        ]);
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_admin_can_view_all_departments_and_create_budget_request_for_a_selected_department(): void
    {
        $admin = User::factory()->admin()->create();
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $staffA = User::factory()->for($departmentA)->create();
        $staffB = User::factory()->for($departmentB)->create();
        $category = $this->createCategory(['name' => 'Utilities']);

        $this->createBudget($staffA, $departmentA, $category);
        $this->createBudget($staffB, $departmentB, $category, ['amount_limit' => 1500]);

        $this->actingAs($admin)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', null)
                ->where('department_scope.is_all_departments', true)
                ->where('department_scope.can_select_department', true)
                ->has('budgets', 2)
                ->has('departments', 3)
            );

        $this->actingAs($admin)
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'create',
                'department_id' => $departmentB->id,
                'category_id' => $category->id,
                'month' => 6,
                'year' => 2026,
                'amount_limit' => 3000,
            ])
            ->assertRedirect();

        $approvalVoucher = ApprovalVoucher::query()->latest('id')->firstOrFail();

        $this->assertSame($admin->id, $approvalVoucher->requested_by);
        $this->assertSame($departmentB->id, $approvalVoucher->department_id);
        $this->assertSame('budget', $approvalVoucher->module->value);
        $this->assertSame('create', $approvalVoucher->action->value);
        $this->assertDatabaseCount('budgets', 2);
    }

    public function test_budget_uniqueness_is_enforced_per_department_for_active_records(): void
    {
        $admin = User::factory()->admin()->create();
        $departmentA = Department::factory()->create();
        $departmentB = Department::factory()->create();
        $category = $this->createCategory();

        $this->createBudget($admin, $departmentA, $category, [
            'month' => 5,
            'year' => 2026,
        ]);

        $this->actingAs($admin)
            ->from(route('budgets.index'))
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'create',
                'department_id' => $departmentA->id,
                'category_id' => $category->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 999,
            ])
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($admin)
            ->post(route('approval-vouchers.store'), [
                'module' => 'budget',
                'action' => 'create',
                'department_id' => $departmentB->id,
                'category_id' => $category->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 999,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_vouchers', [
            'department_id' => $departmentB->id,
            'module' => 'budget',
            'action' => 'create',
        ]);
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Food',
            'type' => 'expense',
        ], $attributes));
    }

    private function createBudget(
        ?User $user = null,
        ?Department $department = null,
        ?Category $category = null,
        array $attributes = [],
    ): Budget {
        $user ??= User::factory()->create();
        $department ??= $user->department;
        $category ??= $this->createCategory();

        return Budget::query()->create(array_merge([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000.00,
        ], $attributes));
    }
}
