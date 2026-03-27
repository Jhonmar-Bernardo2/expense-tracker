<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Department\FinancialManagementDepartmentService;
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

    public function test_guests_are_redirected_to_budget_and_allocation_entry_points(): void
    {
        $category = $this->createCategory();

        $this->get(route('budgets.index'))->assertRedirect(route('login'));

        $this->post(route('approval-vouchers.store'), [
            'module' => 'allocation',
            'action' => 'create',
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 10000,
        ])->assertRedirect(route('login'));

        $this->post(route('budgets.store'), [
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ])->assertRedirect(route('login'));
    }

    public function test_non_financial_staff_cannot_access_budget_pages_or_manage_budget_workflows(): void
    {
        $department = Department::factory()->create(['name' => 'Operations']);
        $staff = User::factory()->for($department)->create();
        $category = $this->createCategory();

        $this->actingAs($staff)
            ->get(route('budgets.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 50000,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 5000,
            ])
            ->assertForbidden();
    }

    public function test_financial_management_user_views_central_budgets_with_allocation_summary_and_org_wide_usage(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $operationsDepartment = Department::factory()->create(['name' => 'Operations']);
        $salesDepartment = Department::factory()->create(['name' => 'Sales']);
        $operationsUser = User::factory()->for($operationsDepartment)->create();
        $salesUser = User::factory()->for($salesDepartment)->create();
        $expenseCategory = $this->createCategory(['name' => 'Food', 'type' => 'expense']);

        $allocation = $this->createAllocation($financialUser, $financialDepartment, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 2000,
        ]);
        $budget = $this->createBudget($financialUser, $financialDepartment, $expenseCategory, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ]);

        Transaction::query()->create([
            'user_id' => $operationsUser->id,
            'department_id' => $operationsDepartment->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'title' => 'Operations lunch',
            'amount' => 400.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'user_id' => $salesUser->id,
            'department_id' => $salesDepartment->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'title' => 'Sales lunch',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        $this->actingAs($financialUser)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budgets/Index')
                ->where('active_allocation.id', $allocation->id)
                ->where('allocation_summary.approved_allocation', 2000)
                ->where('allocation_summary.total_allocated', 1000)
                ->where('allocation_summary.total_unallocated', 1000)
                ->where('allocation_summary.total_spent', 650)
                ->where('allocation_summary.total_remaining', 1350)
                ->where('budgets.0.id', $budget->id)
                ->where('budgets.0.amount_spent', 650)
            );
    }

    public function test_admin_can_view_central_budget_page_but_cannot_manage_allocations_or_category_budgets(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $adminDepartment = Department::factory()->create(['name' => 'Executive']);
        $admin = User::factory()->for($adminDepartment)->admin()->create();
        $category = $this->createCategory(['name' => 'Utilities']);

        $this->actingAs($admin)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_access.can_view_page', true)
                ->where('budget_access.can_manage_category_budgets', false)
                ->where('budget_access.can_request_allocations', false)
            );

        $this->actingAs($admin)
            ->post(route('approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 6,
                'year' => 2026,
                'amount_limit' => 150000,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'month' => 6,
                'year' => 2026,
                'amount_limit' => 7000,
            ])
            ->assertForbidden();
    }

    public function test_category_budgets_require_an_approved_monthly_allocation(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $category = $this->createCategory();

        $this->actingAs($financialUser)
            ->from(route('budgets.index'))
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 2500,
            ])
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('amount_limit');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_category_budgets_cannot_exceed_the_approved_monthly_allocation(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $food = $this->createCategory(['name' => 'Food']);
        $utilities = $this->createCategory(['name' => 'Utilities']);

        $this->createAllocation($financialUser, $financialDepartment, [
            'month' => 5,
            'year' => 2026,
            'amount_limit' => 1000,
        ]);
        $this->createBudget($financialUser, $financialDepartment, $food, [
            'month' => 5,
            'year' => 2026,
            'amount_limit' => 700,
        ]);

        $this->actingAs($financialUser)
            ->from(route('budgets.index'))
            ->post(route('budgets.store'), [
                'category_id' => $utilities->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 400,
            ])
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('amount_limit');

        $this->assertSame(
            1,
            Budget::query()
                ->active()
                ->where('month', 5)
                ->where('year', 2026)
                ->count(),
        );
    }

    public function test_running_the_budget_allocation_migration_backfills_existing_financial_management_budgets(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $food = $this->createCategory(['name' => 'Food']);
        $utilities = $this->createCategory(['name' => 'Utilities']);

        BudgetAllocation::query()->delete();

        $this->createBudget($financialUser, $financialDepartment, $food, [
            'month' => 7,
            'year' => 2026,
            'amount_limit' => 1200,
        ]);
        $this->createBudget($financialUser, $financialDepartment, $utilities, [
            'month' => 7,
            'year' => 2026,
            'amount_limit' => 800,
        ]);

        $migration = require database_path('migrations/2026_03_26_010000_create_budget_allocations_table.php');
        $migration->up();

        $allocation = BudgetAllocation::query()
            ->active()
            ->where('department_id', $financialDepartment->id)
            ->where('month', 7)
            ->where('year', 2026)
            ->sole();

        $this->assertSame('2000.00', (string) $allocation->amount_limit);
    }

    private function financialManagementDepartment(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Food',
            'type' => 'expense',
        ], $attributes));
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
            'amount_limit' => 2500.00,
        ], $attributes));
    }
}
