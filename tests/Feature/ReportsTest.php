<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
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
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_reports_include_budget_comparison_data(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $budget = Budget::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'month' => 3,
            'year' => 2026,
            'amount_limit' => 1000.00,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'title' => 'Groceries',
            'amount' => 325.00,
            'description' => null,
            'transaction_date' => '2026-03-11',
        ]);

        $this->actingAs($user)
            ->get(route('reports.index', ['month' => 3, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('filters.month', 3)
                ->where('filters.year', 2026)
                ->where('breakdowns.budget_vs_actual.0.id', $budget->id)
                ->where('breakdowns.budget_vs_actual.0.category_name', 'Food')
                ->where('breakdowns.budget_vs_actual.0.amount_limit', 1000)
                ->where('breakdowns.budget_vs_actual.0.amount_spent', 325)
                ->where('breakdowns.budget_vs_actual.0.amount_remaining', 675)
            );
    }
}
