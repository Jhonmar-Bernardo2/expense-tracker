<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class BudgetRepository
{
    /**
     * @return Collection<int, Budget>
     */
    public function getForIndex(int $userId, int $month, int $year): Collection
    {
        return $this->queryWithUsage($userId)
            ->where('budgets.month', $month)
            ->where('budgets.year', $year)
            ->orderBy('categories.name')
            ->get();
    }

    /**
     * @return Collection<int, Budget>
     */
    public function getForUser(int $userId): Collection
    {
        return $this->queryWithUsage($userId)
            ->orderByDesc('budgets.year')
            ->orderByDesc('budgets.month')
            ->orderBy('categories.name')
            ->get();
    }

    public function findForUserOrFail(int $userId, int $budgetId): Budget
    {
        return Budget::query()
            ->where('user_id', $userId)
            ->findOrFail($budgetId);
    }

    /**
     * @param  array{category_id: int, month: int, year: int, amount_limit: mixed}  $data
     */
    public function createForUser(int $userId, array $data): Budget
    {
        return Budget::query()->create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'month' => $data['month'],
            'year' => $data['year'],
            'amount_limit' => $data['amount_limit'],
        ]);
    }

    /**
     * @param  array{category_id: int, month: int, year: int, amount_limit: mixed}  $data
     */
    public function update(Budget $budget, array $data): Budget
    {
        $budget->update([
            'category_id' => $data['category_id'],
            'month' => $data['month'],
            'year' => $data['year'],
            'amount_limit' => $data['amount_limit'],
        ]);

        return $budget->refresh();
    }

    public function delete(Budget $budget): void
    {
        $budget->delete();
    }

    /**
     * @return array{total_budgeted: float, total_spent: float, total_remaining: float, categories_over_budget: int}
     */
    public function getMonthlySummary(int $userId, CarbonImmutable $date): array
    {
        $budgets = $this->getForIndex($userId, $date->month, $date->year);

        $totalBudgeted = round($budgets->sum(fn (Budget $budget) => (float) $budget->amount_limit), 2);
        $totalSpent = round($budgets->sum(fn (Budget $budget) => (float) ($budget->amount_spent ?? 0)), 2);
        $categoriesOverBudget = $budgets->filter(
            fn (Budget $budget) => (float) ($budget->amount_spent ?? 0) > (float) $budget->amount_limit
        )->count();

        return [
            'total_budgeted' => $totalBudgeted,
            'total_spent' => $totalSpent,
            'total_remaining' => round($totalBudgeted - $totalSpent, 2),
            'categories_over_budget' => $categoriesOverBudget,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getYearOptions(int $userId, int $currentYear): array
    {
        $budgetYears = Budget::query()
            ->where('user_id', $userId)
            ->pluck('year')
            ->map(fn ($year) => (int) $year);

        $transactionYears = Transaction::query()
            ->where('user_id', $userId)
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM transaction_date) as year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year);

        return collect(range($currentYear - 1, $currentYear + 5))
            ->merge($budgetYears)
            ->merge($transactionYears)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function queryWithUsage(int $userId)
    {
        return Budget::query()
            ->where('budgets.user_id', $userId)
            ->join('categories', 'categories.id', '=', 'budgets.category_id')
            ->where('categories.user_id', $userId)
            ->where('categories.type', TransactionType::Expense->value)
            ->leftJoinSub($this->usageSubquery($userId), 'budget_usage', function ($join) {
                $join->on('budget_usage.category_id', '=', 'budgets.category_id')
                    ->on('budget_usage.usage_month', '=', 'budgets.month')
                    ->on('budget_usage.usage_year', '=', 'budgets.year');
            })
            ->select('budgets.*')
            ->selectRaw('categories.name as category_name, COALESCE(budget_usage.amount_spent, 0) as amount_spent')
            ->with('category');
    }

    private function usageSubquery(int $userId)
    {
        return Transaction::query()
            ->selectRaw('category_id, EXTRACT(MONTH FROM transaction_date) as usage_month, EXTRACT(YEAR FROM transaction_date) as usage_year, SUM(amount) as amount_spent')
            ->where('user_id', $userId)
            ->where('type', TransactionType::Expense->value)
            ->groupBy('category_id')
            ->groupByRaw('EXTRACT(MONTH FROM transaction_date), EXTRACT(YEAR FROM transaction_date)');
    }
}
