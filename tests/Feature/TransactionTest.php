<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
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

        $this->get(route('transactions.index'))->assertRedirect(route('login'));

        $this->post(route('transactions.store'), [
            'department_id' => $transaction->department_id,
            'type' => 'expense',
            'category_id' => $transaction->category_id,
            'title' => 'Lunch',
            'amount' => 150,
            'description' => null,
            'transaction_date' => '2026-03-15',
        ])->assertRedirect(route('login'));

        $this->put(route('transactions.update', $transaction), [
            'department_id' => $transaction->department_id,
            'type' => 'expense',
            'category_id' => $transaction->category_id,
            'title' => 'Dinner',
            'amount' => 200,
            'description' => null,
            'transaction_date' => '2026-03-16',
        ])->assertRedirect(route('login'));

        $this->delete(route('transactions.destroy', $transaction))->assertRedirect(route('login'));
    }

    public function test_staff_user_only_views_transactions_from_their_department(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $otherDepartment = Department::factory()->create(['name' => 'Operations']);
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $expenseCategory = $this->createCategory(['name' => 'Food', 'type' => 'expense']);
        $incomeCategory = $this->createCategory(['name' => 'Salary', 'type' => 'income']);

        $expenseTransaction = $this->createTransaction($user, $department, $expenseCategory, [
            'type' => 'expense',
            'title' => 'Groceries',
            'transaction_date' => '2026-03-10',
        ]);

        $this->createTransaction($otherUser, $otherDepartment, $incomeCategory, [
            'type' => 'income',
            'title' => 'Payroll',
            'transaction_date' => '2026-03-12',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->where('filters.department', $department->id)
                ->where('department_scope.department_id', $department->id)
                ->where('transactions.data.0.id', $expenseTransaction->id)
                ->where('transactions.data.0.department.id', $department->id)
                ->missing('transactions.data.1')
            );
    }

    public function test_staff_cannot_force_transaction_into_another_department(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->for($department)->create();
        $category = $this->createCategory(['name' => 'Food']);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'department_id' => $otherDepartment->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Lunch',
                'amount' => 175.50,
                'description' => 'Team meal',
                'transaction_date' => '2026-03-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'title' => 'Lunch',
            'type' => 'expense',
        ]);
    }

    public function test_admin_can_filter_and_create_transactions_for_any_department(): void
    {
        $admin = User::factory()->admin()->create();
        $departmentA = Department::factory()->create(['name' => 'Finance']);
        $departmentB = Department::factory()->create(['name' => 'Operations']);
        $staffA = User::factory()->for($departmentA)->create();
        $staffB = User::factory()->for($departmentB)->create();
        $category = $this->createCategory(['name' => 'Food']);

        $this->createTransaction($staffA, $departmentA, $category, ['title' => 'Finance lunch']);
        $transactionB = $this->createTransaction($staffB, $departmentB, $category, ['title' => 'Ops lunch']);

        $this->actingAs($admin)
            ->get(route('transactions.index', ['department' => $departmentB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department', $departmentB->id)
                ->where('department_scope.department_id', $departmentB->id)
                ->where('transactions.data.0.id', $transactionB->id)
                ->missing('transactions.data.1')
            );

        $this->actingAs($admin)
            ->post(route('transactions.store'), [
                'department_id' => $departmentB->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Department supplies',
                'amount' => 600,
                'description' => null,
                'transaction_date' => '2026-04-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $admin->id,
            'department_id' => $departmentB->id,
            'title' => 'Department supplies',
        ]);
    }

    public function test_staff_cannot_update_another_departments_transaction(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->for($department)->create();
        $otherUser = User::factory()->for($otherDepartment)->create();
        $category = $this->createCategory();
        $transaction = $this->createTransaction($otherUser, $otherDepartment, $category);

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), [
                'department_id' => $department->id,
                'type' => 'expense',
                'category_id' => $category->id,
                'title' => 'Updated',
                'amount' => 100,
                'description' => null,
                'transaction_date' => '2026-03-20',
            ])
            ->assertNotFound();
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Utilities',
            'type' => 'expense',
        ], $attributes));
    }

    private function createTransaction(
        ?User $user = null,
        ?Department $department = null,
        ?Category $category = null,
        array $attributes = [],
    ): Transaction {
        $user ??= User::factory()->create();
        $department ??= $user->department;
        $category ??= $this->createCategory();

        return Transaction::query()->create(array_merge([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => $category->type->value,
            'title' => 'Utilities',
            'amount' => 250.00,
            'description' => null,
            'transaction_date' => '2026-03-10',
        ], $attributes));
    }
}
