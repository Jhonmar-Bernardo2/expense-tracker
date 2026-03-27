<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BudgetRepository
{
    /**
     * @return Collection<int, Budget>
     */
    public function getForIndex(?int $departmentId, int $month, int $year): Collection
    {
        return $this->queryWithUsage($departmentId)
            ->where('budgets.month', $month)
            ->where('budgets.year', $year)
            ->orderBy('budgets.department_id')
            ->orderBy('categories.name')
            ->get();
    }

    /**
     * @return Collection<int, Budget>
     */
    public function getAccessible(?int $departmentId): Collection
    {
        return $this->queryWithUsage($departmentId)
            ->orderByDesc('budgets.year')
            ->orderByDesc('budgets.month')
            ->orderBy('budgets.department_id')
            ->orderBy('categories.name')
            ->get();
    }

    public function findForViewerOrFail(User $user, int $budgetId): Budget
    {
        return Budget::query()
            ->active()
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->where(
                    'department_id',
                    $user->isFinancialManagement() ? $user->department_id : 0,
                ),
            )
            ->findOrFail($budgetId);
    }

    /**
     * @param  array{category_id: int, month: int, year: int, amount_limit: mixed}  $data
     */
    public function create(
        User $user,
        int $departmentId,
        array $data,
        ?int $originApprovalVoucherId = null,
    ): Budget
    {
        return Budget::query()->create([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'origin_approval_voucher_id' => $originApprovalVoucherId,
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
            'department_id' => $data['department_id'],
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

    public function archive(Budget $budget, ?int $approvalVoucherId = null): Budget
    {
        $budget->update([
            'archived_at' => now(),
            'archived_by_approval_voucher_id' => $approvalVoucherId,
        ]);

        return $budget->refresh();
    }

    /**
     * @return array{
     *     total_budgeted: float,
     *     total_allocated: float,
     *     total_spent: float,
     *     total_remaining: float,
     *     categories_over_budget: int
     * }
     */
    public function getMonthlySummary(?int $departmentId, CarbonImmutable $date): array
    {
        $budgets = $this->getForIndex($departmentId, $date->month, $date->year);

        $totalBudgeted = round($budgets->sum(fn (Budget $budget) => (float) $budget->amount_limit), 2);
        $totalSpent = round($budgets->sum(fn (Budget $budget) => (float) ($budget->amount_spent ?? 0)), 2);
        $categoriesOverBudget = $budgets->filter(
            fn (Budget $budget) => (float) ($budget->amount_spent ?? 0) > (float) $budget->amount_limit
        )->count();

        return [
            'total_budgeted' => $totalBudgeted,
            'total_allocated' => $totalBudgeted,
            'total_spent' => $totalSpent,
            'total_remaining' => round($totalBudgeted - $totalSpent, 2),
            'categories_over_budget' => $categoriesOverBudget,
        ];
    }

    public function sumActiveAmountLimitForPeriod(
        ?int $departmentId,
        int $month,
        int $year,
        ?int $ignoreBudgetId = null,
    ): float {
        return round((float) Budget::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->where('month', $month)
            ->where('year', $year)
            ->when($ignoreBudgetId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreBudgetId))
            ->sum('amount_limit'), 2);
    }

    /**
     * @return array<int, int>
     */
    public function getYearOptions(?int $departmentId, int $currentYear): array
    {
        $budgetYears = Budget::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->pluck('year')
            ->map(fn ($year) => (int) $year);

        $transactionYears = Transaction::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
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

    private function queryWithUsage(?int $departmentId): Builder
    {
        return Budget::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('budgets.department_id', $departmentId))
            ->join('categories', 'categories.id', '=', 'budgets.category_id')
            ->where('categories.type', TransactionType::Expense->value)
            ->leftJoinSub($this->usageSubquery($departmentId), 'budget_usage', function ($join) {
                $join->on('budget_usage.category_id', '=', 'budgets.category_id')
                    ->on('budget_usage.usage_month', '=', 'budgets.month')
                    ->on('budget_usage.usage_year', '=', 'budgets.year');
            })
            ->select('budgets.*')
            ->selectRaw('categories.name as category_name, COALESCE(budget_usage.amount_spent, 0) as amount_spent')
            ->with(['category', 'department']);
    }

    private function usageSubquery(?int $departmentId): Builder
    {
        return Transaction::query()
            ->active()
            ->selectRaw('category_id, EXTRACT(MONTH FROM transaction_date) as usage_month, EXTRACT(YEAR FROM transaction_date) as usage_year, SUM(amount) as amount_spent')
            ->where('type', TransactionType::Expense->value)
            ->groupBy('category_id')
            ->groupByRaw('EXTRACT(MONTH FROM transaction_date), EXTRACT(YEAR FROM transaction_date)');
    }

    public function existsActiveConflict(
        int $departmentId,
        int $categoryId,
        int $month,
        int $year,
        ?int $ignoreBudgetId = null,
    ): bool {
        return Budget::query()
            ->active()
            ->where('department_id', $departmentId)
            ->where('category_id', $categoryId)
            ->where('month', $month)
            ->where('year', $year)
            ->when($ignoreBudgetId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreBudgetId))
            ->exists();
    }
}
