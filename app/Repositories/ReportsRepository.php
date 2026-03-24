<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportsRepository
{
    /**
     * @return array{income: float, expenses: float, balance: float}
     */
    public function getMonthlyTotals(?int $departmentId, int $month, int $year): array
    {
        $income = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Income->value)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->sum('amount');

        $expenses = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Expense->value)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->sum('amount');

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'balance' => round($income - $expenses, 2),
        ];
    }

    /**
     * @return array{income: float, expenses: float, balance: float}
     */
    public function getYearlyTotals(?int $departmentId, int $year): array
    {
        $income = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Income->value)
            ->whereYear('transaction_date', $year)
            ->sum('amount');

        $expenses = (float) $this->baseTransactionQuery($departmentId)
            ->where('type', TransactionType::Expense->value)
            ->whereYear('transaction_date', $year)
            ->sum('amount');

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'balance' => round($income - $expenses, 2),
        ];
    }

    /**
     * @return array<int, array{category_id: int, category_name: string, total: float}>
     */
    public function getExpensesByCategory(?int $departmentId, int $month, int $year): array
    {
        return Transaction::query()
            ->active()
            ->when(
                $departmentId !== null,
                fn (Builder $query) => $query->where('transactions.department_id', $departmentId),
            )
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
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
            ->active()
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

    /**
     * Spending trend for the selected month: daily expense totals.
     *
     * @return array<int, array{date: string, expenses: float}>
     */
    public function getSpendingTrend(?int $departmentId, int $month, int $year): array
    {
        $rows = Transaction::query()
            ->active()
            ->when(
                $departmentId !== null,
                fn (Builder $query) => $query->where('transactions.department_id', $departmentId),
            )
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->groupBy('transactions.transaction_date')
            ->orderBy('transactions.transaction_date')
            ->selectRaw('transactions.transaction_date as date, SUM(transactions.amount) as total')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'expenses' => round((float) $row->total, 2),
            ])
            ->keyBy('date');

        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth();

        return collect($start->daysUntil($end->addDay()))
            ->map(function (CarbonImmutable $date) use ($rows) {
                $key = $date->toDateString();
                $row = $rows->get($key);

                return [
                    'date' => $key,
                    'expenses' => $row['expenses'] ?? 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function getDistinctYears(?int $departmentId): array
    {
        return Transaction::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM transaction_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{month: int, label: string}>
     */
    public function getMonthOptions(): Collection
    {
        return collect(range(1, 12))->map(fn (int $month) => [
            'month' => $month,
            'label' => date('F', mktime(0, 0, 0, $month, 1)),
        ]);
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
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId));
    }
}
