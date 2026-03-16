<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    /**
     * @return array{income: float, expenses: float, balance: float}
     */
    public function getTotals(int $userId): array
    {
        $income = (float) Transaction::query()
            ->where('user_id', $userId)
            ->where('type', TransactionType::Income->value)
            ->sum('amount');

        $expenses = (float) Transaction::query()
            ->where('user_id', $userId)
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
    public function getMonthSummary(int $userId, CarbonImmutable $date): array
    {
        $income = (float) Transaction::query()
            ->where('user_id', $userId)
            ->where('type', TransactionType::Income->value)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount');

        $expenses = (float) Transaction::query()
            ->where('user_id', $userId)
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
    public function getRecentTransactions(int $userId, int $limit = 8): Collection
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->with('category')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, array{category_id: int, category_name: string, total: float}>
     */
    public function getCurrentMonthExpensesByCategory(int $userId, CarbonImmutable $date): array
    {
        return Transaction::query()
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereMonth('transactions.transaction_date', $date->month)
            ->whereYear('transactions.transaction_date', $date->year)
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('categories.user_id', $userId)
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
    public function getIncomeVsExpensesByMonth(int $userId, int $year): array
    {
        $rows = Transaction::query()
            ->where('transactions.user_id', $userId)
            ->whereYear('transactions.transaction_date', $year)
            ->whereHas('category', fn ($query) => $query->where('user_id', $userId))
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
}
