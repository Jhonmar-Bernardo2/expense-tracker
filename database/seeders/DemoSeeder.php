<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $departmentId = (int) $user->department_id;

        $incomeCategories = [
            'Salary',
            'Freelance',
            'Allowance',
            'Interest',
        ];

        $expenseCategories = [
            'Rent',
            'Food',
            'Transportation',
            'Utilities',
            'Internet',
            'Entertainment',
            'Health',
            'Education',
            'Shopping',
        ];

        $categoriesByKey = [];

        foreach ($incomeCategories as $name) {
            $category = Category::query()->firstOrCreate([
                'type' => TransactionType::Income->value,
                'name' => $name,
            ]);

            $categoriesByKey['income:'.$name] = $category;
        }

        foreach ($expenseCategories as $name) {
            $category = Category::query()->firstOrCreate([
                'type' => TransactionType::Expense->value,
                'name' => $name,
            ]);

            $categoriesByKey['expense:'.$name] = $category;
        }

        $now = CarbonImmutable::now()->startOfDay();
        $start = $now->subMonths(5)->startOfMonth();

        $monthCursor = $start;
        while ($monthCursor <= $now) {
            $year = $monthCursor->year;
            $month = $monthCursor->month;

            // Primary income: salary every month.
            Transaction::query()->create([
                'user_id' => $userId,
                'department_id' => $departmentId,
                'category_id' => $categoriesByKey['income:Salary']->id,
                'type' => TransactionType::Income->value,
                'title' => 'Monthly salary',
                'amount' => 25000 + (($month % 3) * 750),
                'description' => 'Payroll deposit',
                'transaction_date' => $monthCursor->setDay(1)->addDays(3)->toDateString(),
            ]);

            // Occasional freelance.
            if ($month % 2 === 0) {
                Transaction::query()->create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'category_id' => $categoriesByKey['income:Freelance']->id,
                    'type' => TransactionType::Income->value,
                    'title' => 'Freelance project',
                    'amount' => 3500 + (($month % 4) * 500),
                    'description' => 'Side gig payout',
                    'transaction_date' => $monthCursor->setDay(1)->addDays(18)->toDateString(),
                ]);
            }

            // Fixed expenses.
            Transaction::query()->create([
                'user_id' => $userId,
                'department_id' => $departmentId,
                'category_id' => $categoriesByKey['expense:Rent']->id,
                'type' => TransactionType::Expense->value,
                'title' => 'Rent',
                'amount' => 8500,
                'description' => null,
                'transaction_date' => $monthCursor->setDay(1)->addDays(1)->toDateString(),
            ]);

            Transaction::query()->create([
                'user_id' => $userId,
                'department_id' => $departmentId,
                'category_id' => $categoriesByKey['expense:Internet']->id,
                'type' => TransactionType::Expense->value,
                'title' => 'Internet bill',
                'amount' => 1499,
                'description' => null,
                'transaction_date' => $monthCursor->setDay(1)->addDays(8)->toDateString(),
            ]);

            Transaction::query()->create([
                'user_id' => $userId,
                'department_id' => $departmentId,
                'category_id' => $categoriesByKey['expense:Utilities']->id,
                'type' => TransactionType::Expense->value,
                'title' => 'Utilities',
                'amount' => 2100 + (($month % 3) * 150),
                'description' => null,
                'transaction_date' => $monthCursor->setDay(1)->addDays(10)->toDateString(),
            ]);

            // Weekly-ish food and transport.
            foreach ([4, 11, 18, 25] as $day) {
                $date = $monthCursor->setDay(1)->addDays($day);

                if ($date->month !== $month) {
                    continue;
                }

                Transaction::query()->create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'category_id' => $categoriesByKey['expense:Food']->id,
                    'type' => TransactionType::Expense->value,
                    'title' => 'Groceries',
                    'amount' => 900 + (($day % 3) * 120),
                    'description' => null,
                    'transaction_date' => $date->toDateString(),
                ]);

                Transaction::query()->create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'category_id' => $categoriesByKey['expense:Transportation']->id,
                    'type' => TransactionType::Expense->value,
                    'title' => 'Transport',
                    'amount' => 180 + (($day % 4) * 30),
                    'description' => null,
                    'transaction_date' => $date->addDay()->toDateString(),
                ]);
            }

            // Some variety for charts.
            if ($month % 3 === 1) {
                Transaction::query()->create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'category_id' => $categoriesByKey['expense:Entertainment']->id,
                    'type' => TransactionType::Expense->value,
                    'title' => 'Movie night',
                    'amount' => 350,
                    'description' => null,
                    'transaction_date' => $monthCursor->setDay(1)->addDays(15)->toDateString(),
                ]);
            }

            if ($month % 3 === 2) {
                Transaction::query()->create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'category_id' => $categoriesByKey['expense:Health']->id,
                    'type' => TransactionType::Expense->value,
                    'title' => 'Pharmacy',
                    'amount' => 420,
                    'description' => null,
                    'transaction_date' => $monthCursor->setDay(1)->addDays(14)->toDateString(),
                ]);
            }

            if ($month % 4 === 0) {
                Transaction::query()->create([
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'category_id' => $categoriesByKey['expense:Shopping']->id,
                    'type' => TransactionType::Expense->value,
                    'title' => 'Household items',
                    'amount' => 1200,
                    'description' => null,
                    'transaction_date' => $monthCursor->setDay(1)->addDays(20)->toDateString(),
                ]);
            }

            $monthCursor = $monthCursor->addMonth();
        }
    }
}
