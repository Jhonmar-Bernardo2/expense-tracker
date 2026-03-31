<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryBudgetPreset;
use App\Models\Department;
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

        $this->get(route('admin.categories.index'))->assertRedirect(route('login'));

        $this->post(route('admin.categories.store'), [
            'name' => 'Food',
            'type' => 'expense',
        ])->assertRedirect(route('login'));

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Dining',
            'type' => 'expense',
        ])->assertRedirect(route('login'));

        $this->delete(route('admin.categories.destroy', $category))->assertRedirect(route('login'));
    }

    public function test_staff_users_cannot_access_category_management(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory();

        $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'Food', 'type' => 'expense'])->assertForbidden();
        $this->actingAs($user)->put(route('admin.categories.update', $category), ['name' => 'Dining', 'type' => 'expense'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.categories.destroy', $category))->assertForbidden();
    }

    public function test_admin_can_view_and_create_global_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->createCategory([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Categories/Index')
                ->where('filters.type', null)
                ->has('categories', 1)
                ->where('categories.0.id', $category->id)
                ->where('categories.0.name', 'Food')
                ->where('categories.0.type', 'expense')
                ->where('categories.0.has_budget_preset', false)
                ->where('categories.0.budget_preset_count', 0)
                ->has('categories.0.budget_presets', 0)
            );

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => '   Food   Allowance   ',
                'type' => 'income',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Food Allowance',
            'type' => 'income',
        ]);
    }

    public function test_category_names_are_unique_globally_per_type(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createCategory([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->post(route('admin.categories.store'), [
                'name' => 'Food',
                'type' => 'expense',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_admin_cannot_delete_category_that_has_related_records(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $user = User::factory()->for($department)->create();
        $category = $this->createCategory([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Lunch',
            'amount' => 200.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ]);

        Budget::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 8000.00,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'This category cannot be deleted because it is already used by transactions, budgets, or budget presets.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_can_view_budget_preset_metadata_and_cannot_delete_category_with_preset(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->createCategory([
            'name' => 'Travel',
            'type' => 'expense',
        ]);

        $preset = $this->createPreset('Flight default', [
            [
                'category_id' => $category->id,
                'amount_limit' => 4200.00,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.id', $category->id)
                ->where('categories.0.has_budget_preset', true)
                ->where('categories.0.budget_preset_count', 1)
                ->where('categories.0.budget_presets.0.id', $preset->id)
                ->where('categories.0.budget_presets.0.name', 'Flight default')
                ->where('categories.0.budget_presets.0.amount_limit', 4200)
                ->where('categories.0.can_delete', false)
            );

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'This category cannot be deleted because it is already used by transactions, budgets, or budget presets.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Utilities',
            'type' => 'expense',
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
