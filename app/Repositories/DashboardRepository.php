<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    /**
     * @return array{income: float, expenses: float, balance: float}
     */
    public function getTotals(?int $departmentId): array
    {
        $income = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Income->value)
            ->sum('amount');

        $expenses = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Expense->value)
            ->sum('amount');

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'balance' => round($income - $expenses, 2),
        ];
    }

    /**
     * @return array{month: int, year: int, income: float, expenses: float, balance: float}
     */
    public function getMonthSummary(?int $departmentId, CarbonImmutable $date): array
    {
        $income = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Income->value)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount');

        $expenses = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Expense->value)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount');

        return [
            'month' => $date->month,
            'year' => $date->year,
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'balance' => round($income - $expenses, 2),
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getRecentTransactions(?int $departmentId, int $limit = 8): Collection
    {
        return $this->baseTransactionQuery($departmentId)
            ->with(['category', 'department'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, array{category_id: int, category_name: string, total: float}>
     */
    public function getCurrentMonthExpensesByCategory(?int $departmentId, CarbonImmutable $date): array
    {
        return Transaction::query()
            ->when(
                $departmentId !== null,
                fn (Builder $query) => $query->where('transactions.department_id', $departmentId),
            )
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereMonth('transactions.transaction_date', $date->month)
            ->whereYear('transactions.transaction_date', $date->year)
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc('total')
            ->selectRaw('transactions.category_id as category_id, categories.name as category_name, SUM(transactions.amount) as total')
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->category_id,
                'category_name' => (string) $row->category_name,
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array{month: int, income: float, expenses: float}>
     */
    public function getIncomeVsExpensesByMonth(?int $departmentId, int $year): array
    {
        $rows = Transaction::query()
            ->when(
                $departmentId !== null,
                fn (Builder $query) => $query->where('transactions.department_id', $departmentId),
            )
            ->whereYear('transactions.transaction_date', $year)
            ->groupByRaw('EXTRACT(MONTH FROM transactions.transaction_date), transactions.type')
            ->selectRaw('EXTRACT(MONTH FROM transactions.transaction_date) as month, transactions.type as type, SUM(transactions.amount) as total')
            ->get();

        $base = collect(range(1, 12))->map(fn ($month) => [
            'month' => $month,
            'income' => 0.0,
            'expenses' => 0.0,
        ])->keyBy('month');

        foreach ($rows as $row) {
            $month = (int) $row->month;
            $type = $this->normalizeTransactionType($row->type);
            $total = round((float) $row->total, 2);

            if (!$base->has($month)) {
                continue;
            }

            $entry = $base->get($month);

            if ($type === TransactionType::Income->value) {
                $entry['income'] = $total;
            }

            if ($type === TransactionType::Expense->value) {
                $entry['expenses'] = $total;
            }

            $base->put($month, $entry);
        }

        return $base->values()->all();
    }

    private function normalizeTransactionType(mixed $type): ?string
    {
        if ($type instanceof TransactionType) {
            return $type->value;
        }

        if (is_string($type) && $type !== '') {
            return $type;
        }

        return null;
    }

    private function baseTransactionQuery(?int $departmentId): Builder
    {
        return Transaction::query()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId));
    }
}
