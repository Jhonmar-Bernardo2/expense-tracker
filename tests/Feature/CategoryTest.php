<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $category = $this->createCategory();

        $this->get(route('categories.index'))
            ->assertRedirect(route('login'));

        $this->post(route('categories.store'), [
            'name' => 'Food',
            'type' => 'expense',
        ])->assertRedirect(route('login'));

        $this->put(route('categories.update', $category), [
            'name' => 'Dining',
            'type' => 'expense',
        ])->assertRedirect(route('login'));

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_category_index_with_expected_props(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Lunch',
            'amount' => 150.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Budget::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 5000.00,
        ]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories/Index')
                ->where('filters.type', null)
                ->has('types', 2)
                ->has('categories', 1)
                ->where('categories.0.id', $category->id)
                ->where('categories.0.name', 'Food')
                ->where('categories.0.type', 'expense')
                ->where('categories.0.transaction_count', 1)
                ->where('categories.0.budget_count', 1)
                ->where('categories.0.can_delete', false)
            );
    }

    public function test_user_can_create_a_category_and_name_is_normalized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => '   Food   Allowance   ',
                'type' => 'income',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Food Allowance',
            'type' => 'income',
        ]);
    }

    public function test_user_cannot_create_a_duplicate_category_with_same_name_and_type(): void
    {
        $user = User::factory()->create();
        $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->post(route('categories.store'), [
                'name' => 'Food',
                'type' => 'expense',
            ])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_update_their_own_category_without_false_duplicate_failure(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => '  Food  ',
                'type' => 'expense',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
        ]);
    }

    public function test_user_cannot_update_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory($otherUser);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Updated',
                'type' => 'expense',
            ])
            ->assertNotFound();
    }

    public function test_user_cannot_delete_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory($otherUser);

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertNotFound();
    }

    public function test_user_can_delete_an_unused_category(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user);

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_user_cannot_delete_category_that_has_related_records(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Lunch',
            'amount' => 200.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error', 'This category cannot be deleted because it is already used by transactions or budgets.');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_user_cannot_delete_category_that_has_budget_records(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory($user, [
            'name' => 'Rent',
            'type' => 'expense',
        ]);

        Budget::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 8000.00,
        ]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error', 'This category cannot be deleted because it is already used by transactions or budgets.');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_index_filter_only_returns_matching_user_scoped_categories(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $expenseCategory = $this->createCategory($user, [
            'name' => 'Food',
            'type' => 'expense',
        ]);
        $this->createCategory($user, [
            'name' => 'Salary',
            'type' => 'income',
        ]);
        $this->createCategory($otherUser, [
            'name' => 'Other Food',
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->get(route('categories.index', ['type' => 'expense']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'expense')
                ->has('categories', 1)
                ->where('categories.0.id', $expenseCategory->id)
                ->where('categories.0.name', 'Food')
                ->where('categories.0.type', 'expense')
            );
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
}
