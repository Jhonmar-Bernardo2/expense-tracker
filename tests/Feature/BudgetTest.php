<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
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
        $budget = $this->createBudget();

        $this->get(route('budgets.index'))
            ->assertRedirect(route('login'));

        $this->post(route('budgets.store'), [
            'category_id' => $budget->category_id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ])->assertRedirect(route('login'));

        $this->put(route('budgets.update', $budget), [
            'category_id' => $budget->category_id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1500,
        ])->assertRedirect(route('login'));

        $this->delete(route('budgets.destroy', $budget))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_budget_index_with_usage_props(): void
    {
        CarbonImmutable::setTestNow('2026-03-15 12:00:00');

        $user = User::factory()->create();
        $expenseCategory = $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);
        $incomeCategory = $this->createCategory($user, [
            'name' => 'Salary',
            'type' => 'income',
        ]);
        $budget = $this->createBudget($user, $expenseCategory, [
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'title' => 'Groceries',
            'amount' => 400.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'title' => 'Previous month',
            'amount' => 75.00,
            'description' => null,
            'transaction_date' => '2026-02-28',
        ]);

        $this->actingAs($user)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budgets/Index')
                ->where('filters.month', 3)
                ->where('filters.year', 2026)
                ->has('budgets', 1)
                ->has('categories', 1)
                ->where('categories.0.id', $expenseCategory->id)
                ->where('categories.0.name', 'Food')
                ->where('budgets.0.id', $budget->id)
                ->where('budgets.0.category_name', 'Food')
                ->where('budgets.0.amount_limit', 1000)
                ->where('budgets.0.amount_spent', 400)
                ->where('budgets.0.amount_remaining', 600)
                ->where('budgets.0.percentage_used', 40)
                ->where('budgets.0.is_over_budget', false)
                ->missing('categories.1')
            );

        $this->assertDatabaseHas('categories', [
            'id' => $incomeCategory->id,
            'type' => 'income',
        ]);
    }

    public function test_user_can_create_update_and_delete_a_budget(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, ['name' => 'Utilities']);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 2500,
            ])
            ->assertRedirect();

        $budget = Budget::query()->firstOrFail();

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'month' => 4,
            'year' => 2026,
            'amount_limit' => 2500.00,
        ]);

        $this->actingAs($user)
            ->put(route('budgets.update', $budget), [
                'category_id' => $category->id,
                'month' => 4,
                'year' => 2026,
                'amount_limit' => 3000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'amount_limit' => 3000.00,
        ]);

        $this->actingAs($user)
            ->delete(route('budgets.destroy', $budget))
            ->assertRedirect();

        $this->assertDatabaseMissing('budgets', [
            'id' => $budget->id,
        ]);
    }

    public function test_user_cannot_create_budget_for_another_users_category_or_income_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = $this->createCategory($otherUser);
        $incomeCategory = $this->createCategory($user, [
            'name' => 'Salary',
            'type' => 'income',
        ]);

        $this->actingAs($user)
            ->from(route('budgets.index'))
            ->post(route('budgets.store'), [
                'category_id' => $otherCategory->id,
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 1200,
            ])
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->from(route('budgets.index'))
            ->post(route('budgets.store'), [
                'category_id' => $incomeCategory->id,
                'month' => 3,
                'year' => 2026,
                'amount_limit' => 1200,
            ])
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('category_id');
    }

    public function test_user_cannot_create_duplicate_budget_for_same_category_month_and_year(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user);
        $this->createBudget($user, $category, [
            'month' => 5,
            'year' => 2026,
        ]);

        $this->actingAs($user)
            ->from(route('budgets.index'))
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'month' => 5,
                'year' => 2026,
                'amount_limit' => 999,
            ])
            ->assertRedirect(route('budgets.index'))
            ->assertSessionHasErrors('category_id');
    }

    public function test_budget_becomes_over_budget_when_expense_transactions_exceed_limit(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, ['name' => 'Dining']);
        $budget = $this->createBudget($user, $category, [
            'month' => 6,
            'year' => 2026,
            'amount_limit' => 500,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Dinner',
            'amount' => 650.00,
            'description' => null,
            'transaction_date' => '2026-06-04',
        ]);

        $this->actingAs($user)
            ->get(route('budgets.index', ['month' => 6, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budgets.0.id', $budget->id)
                ->where('budgets.0.amount_spent', 650)
                ->where('budgets.0.amount_remaining', -150)
                ->where('budgets.0.is_over_budget', true)
            );
    }

    private function createCategory(?User $user = null, array $attributes = []): Category
    {
        $user ??= User::factory()->create();

        return Category::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
        ], $attributes));
    }

    private function createBudget(?User $user = null, ?Category $category = null, array $attributes = []): Budget
    {
        $user ??= User::factory()->create();
        $category ??= $this->createCategory($user);

        return Budget::query()->create(array_merge([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000.00,
        ], $attributes));
    }
}
