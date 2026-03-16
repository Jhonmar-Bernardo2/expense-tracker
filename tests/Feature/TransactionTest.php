<?php

namespace Tests\Feature;

use App\Models\Category;
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
        $transaction = $this->createTransaction();

        $this->get(route('transactions.index'))
            ->assertRedirect(route('login'));

        $this->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $transaction->category_id,
            'title' => 'Lunch',
            'amount' => 150,
            'description' => null,
            'transaction_date' => '2026-03-15',
        ])->assertRedirect(route('login'));

        $this->put(route('transactions.update', $transaction), [
            'type' => 'expense',
            'category_id' => $transaction->category_id,
            'title' => 'Dinner',
            'amount' => 200,
            'description' => null,
            'transaction_date' => '2026-03-16',
        ])->assertRedirect(route('login'));

        $this->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_filtered_transactions(): void
    {
        $user = User::factory()->create();
        $expenseCategory = $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);
        $incomeCategory = $this->createCategory($user, [
            'name' => 'Salary',
            'type' => 'income',
        ]);

        $expenseTransaction = $this->createTransaction($user, $expenseCategory, [
            'type' => 'expense',
            'title' => 'Groceries',
            'transaction_date' => '2026-03-10',
        ]);

        $this->createTransaction($user, $incomeCategory, [
            'type' => 'income',
            'title' => 'Payroll',
            'transaction_date' => '2026-03-12',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', [
                'type' => 'expense',
                'category' => $expenseCategory->id,
                'month' => 3,
                'year' => 2026,
                'search' => 'Grocer',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->where('filters.type', 'expense')
                ->where('filters.category', $expenseCategory->id)
                ->where('filters.month', 3)
                ->where('filters.year', 2026)
                ->where('filters.search', 'Grocer')
                ->where('transactions.data.0.id', $expenseTransaction->id)
                ->where('transactions.data.0.title', 'Groceries')
            );
    }

    public function test_transaction_index_rejects_other_users_category_filter(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = $this->createCategory($otherUser);

        $this->actingAs($user)
            ->from(route('transactions.index'))
            ->get(route('transactions.index', ['category' => $otherCategory->id]))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_user_can_create_update_and_delete_their_own_transaction(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, ['name' => 'Food']);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Lunch',
                'amount' => 175.50,
                'description' => 'Team meal',
                'transaction_date' => '2026-03-15',
            ])
            ->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Lunch',
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), [
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Dinner',
                'amount' => 210,
                'description' => null,
                'transaction_date' => '2026-03-16',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'title' => 'Dinner',
            'amount' => 210,
        ]);

        $this->actingAs($user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect();

        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_user_cannot_update_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $usersCategory = $this->createCategory($user);
        $transaction = $this->createTransaction($otherUser);

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), [
                'type' => 'expense',
                'category_id' => $usersCategory->id,
                'title' => 'Updated',
                'amount' => 100,
                'description' => null,
                'transaction_date' => '2026-03-20',
            ])
            ->assertNotFound();
    }

    private function createCategory(?User $user = null, array $attributes = []): Category
    {
        $user ??= User::factory()->create();

        return Category::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Utilities',
            'type' => 'expense',
        ], $attributes));
    }

    private function createTransaction(?User $user = null, ?Category $category = null, array $attributes = []): Transaction
    {
        $user ??= User::factory()->create();
        $category ??= $this->createCategory($user);

        return Transaction::query()->create(array_merge([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => $category->type->value,
            'title' => 'Utilities',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ], $attributes));
    }
}
