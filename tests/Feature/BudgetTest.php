<?php

namespace Tests\Feature;

use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\CategoryBudgetPreset;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\BudgetAllocationSummaryService;
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

        $this->get(route('finance.budgets.index'))->assertRedirect(route('login'));
        $this->get(route('finance.category-budget-presets.index'))->assertRedirect(route('login'));

        $this->post(route('app.approval-vouchers.store'), [
            'module' => 'allocation',
            'action' => 'create',
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 10000,
        ])->assertRedirect(route('login'));

        $this->post(route('finance.budgets.store'), [
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
            ->get(route('finance.budgets.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('finance.category-budget-presets.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 50000,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('finance.budgets.store'), [
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
            ->get(route('finance.budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Budgets/Index')
                ->where('active_allocation.id', $allocation->id)
                ->where('allocation_summary.approved_allocation', 2000)
                ->where('allocation_summary.total_approved_budget', 2000)
                ->where('allocation_summary.total_allocated', 1000)
                ->where('allocation_summary.total_allocated_budget', 1000)
                ->where('allocation_summary.total_unallocated', 1000)
                ->where('allocation_summary.remaining_budget', 1000)
                ->where('allocation_summary.total_spent', 650)
                ->where('allocation_summary.total_remaining', 1350)
                ->where('allocation_summary.remaining_after_spending', 1350)
                ->where('allocation_summary.can_allocate_category_budgets', true)
                ->where('allocation_summary.allocation_block_message', null)
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
            ->get(route('finance.budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_access.can_view_page', true)
                ->where('budget_access.can_manage_category_budgets', false)
                ->where('budget_access.can_request_allocations', false)
            );

        $this->actingAs($admin)
            ->get(route('finance.category-budget-presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/BudgetPresets/Index')
                ->where('budget_access.can_view_page', true)
                ->where('budget_access.can_manage_category_budgets', false)
            );

        $this->actingAs($admin)
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'create',
                'month' => 6,
                'year' => 2026,
                'amount_limit' => 150000,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('finance.budgets.store'), [
                'category_id' => $category->id,
                'month' => 6,
                'year' => 2026,
                'amount_limit' => 7000,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('finance.category-budget-presets.store'), [
                'name' => 'Utilities default',
                'items' => [
                    [
                        'category_id' => $category->id,
                        'amount_limit' => 1500,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_financial_management_can_view_budget_presets_page(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $food = $this->createCategory(['name' => 'Food']);
        $travel = $this->createCategory(['name' => 'Travel']);
        $preset = $this->createPreset('Operations bundle', [
            [
                'category_id' => $food->id,
                'amount_limit' => 2500,
            ],
            [
                'category_id' => $travel->id,
                'amount_limit' => 3200,
            ],
        ]);

        $this->actingAs($financialUser)
            ->get(route('finance.category-budget-presets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/BudgetPresets/Index')
                ->where('budget_presets.0.id', $preset->id)
                ->where('budget_presets.0.name', 'Operations bundle')
                ->where('budget_presets.0.items.0.category_id', $food->id)
                ->where('budget_presets.0.items.1.category_id', $travel->id)
                ->where('categories.0.id', $food->id)
                ->where('categories.1.id', $travel->id)
            );
    }

    public function test_financial_management_can_create_update_and_delete_category_budget_presets(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $utilities = $this->createCategory(['name' => 'Utilities']);
        $travel = $this->createCategory(['name' => 'Travel']);
        $food = $this->createCategory(['name' => 'Food']);

        $this->actingAs($financialUser)
            ->post(route('finance.category-budget-presets.store'), [
                'name' => 'Operations starter',
                'items' => [
                    [
                        'category_id' => $utilities->id,
                        'amount_limit' => 2500,
                    ],
                    [
                        'category_id' => $travel->id,
                        'amount_limit' => 4000,
                    ],
                ],
            ])
            ->assertRedirect();

        $preset = CategoryBudgetPreset::query()->sole();

        $this->assertDatabaseHas('category_budget_presets', [
            'id' => $preset->id,
            'name' => 'Operations starter',
        ]);
        $this->assertDatabaseHas('category_budget_preset_items', [
            'category_budget_preset_id' => $preset->id,
            'category_id' => $utilities->id,
            'amount_limit' => 2500,
        ]);
        $this->assertDatabaseHas('category_budget_preset_items', [
            'category_budget_preset_id' => $preset->id,
            'category_id' => $travel->id,
            'amount_limit' => 4000,
        ]);

        $this->actingAs($financialUser)
            ->put(route('finance.category-budget-presets.update', $preset), [
                'name' => 'Operations backup',
                'items' => [
                    [
                        'category_id' => $utilities->id,
                        'amount_limit' => 3200,
                    ],
                    [
                        'category_id' => $food->id,
                        'amount_limit' => 1800,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('category_budget_presets', [
            'id' => $preset->id,
            'name' => 'Operations backup',
        ]);
        $this->assertDatabaseHas('category_budget_preset_items', [
            'category_budget_preset_id' => $preset->id,
            'category_id' => $utilities->id,
            'amount_limit' => 3200,
        ]);
        $this->assertDatabaseHas('category_budget_preset_items', [
            'category_budget_preset_id' => $preset->id,
            'category_id' => $food->id,
            'amount_limit' => 1800,
        ]);
        $this->assertDatabaseMissing('category_budget_preset_items', [
            'category_budget_preset_id' => $preset->id,
            'category_id' => $travel->id,
        ]);

        $this->actingAs($financialUser)
            ->delete(route('finance.category-budget-presets.destroy', $preset))
            ->assertRedirect();

        $this->assertDatabaseCount('category_budget_presets', 0);
        $this->assertDatabaseCount('category_budget_preset_items', 0);
    }

    public function test_regular_staff_cannot_manage_category_budget_presets(): void
    {
        $operationsDepartment = Department::factory()->create(['name' => 'Operations']);
        $staff = User::factory()->for($operationsDepartment)->create();
        $category = $this->createCategory(['name' => 'Utilities']);
        $preset = $this->createPreset('Utilities default', [
            [
                'category_id' => $category->id,
                'amount_limit' => 1800,
            ],
        ]);

        $this->actingAs($staff)
            ->post(route('finance.category-budget-presets.store'), [
                'name' => 'Operations utility',
                'items' => [
                    [
                        'category_id' => $category->id,
                        'amount_limit' => 1500,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('finance.category-budget-presets.update', $preset), [
                'name' => 'Operations utility',
                'items' => [
                    [
                        'category_id' => $category->id,
                        'amount_limit' => 2100,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('finance.category-budget-presets.destroy', $preset))
            ->assertForbidden();
    }

    public function test_category_budget_presets_require_unique_names_distinct_expense_categories_and_positive_amounts(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $expenseCategory = $this->createCategory(['name' => 'Food']);
        $secondExpenseCategory = $this->createCategory(['name' => 'Travel']);
        $incomeCategory = $this->createCategory([
            'name' => 'Allowance',
            'type' => 'income',
        ]);

        $this->createPreset('Starter preset', [
            [
                'category_id' => $expenseCategory->id,
                'amount_limit' => 1500,
            ],
        ]);

        $this->actingAs($financialUser)
            ->from(route('finance.category-budget-presets.index'))
            ->post(route('finance.category-budget-presets.store'), [
                'name' => 'Starter preset',
                'items' => [
                    [
                        'category_id' => $secondExpenseCategory->id,
                        'amount_limit' => 2200,
                    ],
                ],
            ])
            ->assertRedirect(route('finance.category-budget-presets.index'))
            ->assertSessionHasErrors('name');

        $this->actingAs($financialUser)
            ->from(route('finance.category-budget-presets.index'))
            ->post(route('finance.category-budget-presets.store'), [
                'name' => 'Operations bundle',
                'items' => [
                    [
                        'category_id' => $expenseCategory->id,
                        'amount_limit' => 1800,
                    ],
                    [
                        'category_id' => $expenseCategory->id,
                        'amount_limit' => 900,
                    ],
                ],
            ])
            ->assertRedirect(route('finance.category-budget-presets.index'))
            ->assertSessionHasErrors('items.1.category_id');

        $this->actingAs($financialUser)
            ->from(route('finance.category-budget-presets.index'))
            ->post(route('finance.category-budget-presets.store'), [
                'name' => 'Income preset',
                'items' => [
                    [
                        'category_id' => $incomeCategory->id,
                        'amount_limit' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('finance.category-budget-presets.index'))
            ->assertSessionHasErrors([
                'items.0.category_id',
                'items.0.amount_limit',
            ]);
    }

    public function test_budget_index_includes_category_budget_preset_metadata_in_category_options(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $category = $this->createCategory(['name' => 'Food']);
        $travel = $this->createCategory(['name' => 'Travel']);
        $preset = $this->createPreset('Starter preset', [
            [
                'category_id' => $category->id,
                'amount_limit' => 2750,
            ],
            [
                'category_id' => $travel->id,
                'amount_limit' => 4100,
            ],
        ]);

        $this->actingAs($financialUser)
            ->get(route('finance.budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Budgets/Index')
                ->where('budget_presets.0.id', $preset->id)
                ->where('budget_presets.0.name', 'Starter preset')
                ->where('budget_presets.0.items.0.category_id', $category->id)
                ->where('budget_presets.0.items.0.amount_limit', 2750)
                ->where('categories.0.id', $category->id)
                ->where('categories.0.name', $category->name)
                ->where('categories.0.budget_presets.0.id', $preset->id)
                ->where('categories.0.budget_presets.0.name', 'Starter preset')
                ->where('categories.0.budget_presets.0.amount_limit', 2750)
            );
    }

    public function test_financial_management_can_add_multiple_category_budgets_from_a_preset(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $food = $this->createCategory(['name' => 'Food']);
        $travel = $this->createCategory(['name' => 'Travel']);
        $preset = $this->createPreset('Operations bundle', [
            [
                'category_id' => $food->id,
                'amount_limit' => 2500,
            ],
            [
                'category_id' => $travel->id,
                'amount_limit' => 3200,
            ],
        ]);

        $this->createAllocation($financialUser, $financialDepartment, [
            'month' => 5,
            'year' => 2026,
            'amount_limit' => 10000,
        ]);

        $this->actingAs($financialUser)
            ->from(route('finance.budgets.index'))
            ->post(route('finance.budgets.store'), [
                'source' => 'preset',
                'preset_id' => $preset->id,
                'month' => 5,
                'year' => 2026,
            ])
            ->assertRedirect(route('finance.budgets.index'))
            ->assertSessionHas('success', 'Category budgets added from preset.');

        $this->assertDatabaseHas('budgets', [
            'department_id' => $financialDepartment->id,
            'category_id' => $food->id,
            'month' => 5,
            'year' => 2026,
            'amount_limit' => 2500,
        ]);
        $this->assertDatabaseHas('budgets', [
            'department_id' => $financialDepartment->id,
            'category_id' => $travel->id,
            'month' => 5,
            'year' => 2026,
            'amount_limit' => 3200,
        ]);
    }

    public function test_financial_management_cannot_submit_monthly_budget_removal_requests(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $allocation = $this->createAllocation($financialUser, $financialDepartment, [
            'month' => 6,
            'year' => 2026,
            'amount_limit' => 150000,
        ]);

        $this->actingAs($financialUser)
            ->from(route('finance.budgets.index'))
            ->post(route('app.approval-vouchers.store'), [
                'module' => 'allocation',
                'action' => 'delete',
                'target_id' => $allocation->id,
                'auto_submit' => true,
            ])
            ->assertRedirect(route('finance.budgets.index'))
            ->assertSessionHasErrors([
                'action' => 'Monthly budget removal requests are no longer supported.',
            ]);

        $this->assertDatabaseCount('approval_vouchers', 0);
        $this->assertNull($allocation->fresh()->archived_at);
    }

    public function test_category_budgets_require_an_approved_monthly_allocation(): void
    {
        $financialDepartment = $this->financialManagementDepartment();
        $financialUser = User::factory()->for($financialDepartment)->create();
        $category = $this->createCategory();

        $this->actingAs($financialUser)
            ->from(route('finance.budgets.index'))
            ->post(route('finance.budgets.store'), [
                'category_id' => $category->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 2500,
            ])
            ->assertRedirect(route('finance.budgets.index'))
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
            ->from(route('finance.budgets.index'))
            ->post(route('finance.budgets.store'), [
                'category_id' => $utilities->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 400,
            ])
            ->assertRedirect(route('finance.budgets.index'))
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

    public function test_category_budget_shows_exact_error_when_no_department_budget_is_left_to_allocate(): void
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
            'amount_limit' => 1000,
        ]);

        $this->actingAs($financialUser)
            ->from(route('finance.budgets.index'))
            ->post(route('finance.budgets.store'), [
                'category_id' => $utilities->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 1,
            ])
            ->assertRedirect(route('finance.budgets.index'))
            ->assertSessionHasErrors([
                'amount_limit' => BudgetAllocationSummaryService::NO_AVAILABLE_BUDGET_MESSAGE,
            ]);

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

    /**
     * @param  list<array{category_id: int, amount_limit: int|float|string}>  $items
     */
    private function createPreset(string $name, array $items): CategoryBudgetPreset
    {
        $preset = CategoryBudgetPreset::query()->create([
            'name' => $name,
        ]);

        $preset->categories()->sync(
            collect($items)
                ->mapWithKeys(fn (array $item) => [
                    $item['category_id'] => [
                        'amount_limit' => $item['amount_limit'],
                    ],
                ])
                ->all(),
        );

        return $preset->fresh(['items.category', 'categories']);
    }
}
