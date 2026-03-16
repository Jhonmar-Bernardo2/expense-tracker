<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionRepository
{
    /**
     * @param  array{type?: ?TransactionType, category_id?: ?int, month?: ?int, year?: ?int, search?: ?string}  $filters
     */
    public function getForIndex(int $userId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        return Transaction::query()
            ->where('user_id', $userId)
            ->whereHas('category', fn ($query) => $query->where('user_id', $userId))
            ->with('category')
            ->when($filters['type'] ?? null, fn ($query, TransactionType $type) => $query->where('type', $type->value))
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['month'] ?? null, fn ($query, int $month) => $query->whereMonth('transaction_date', $month))
            ->when($filters['year'] ?? null, fn ($query, int $year) => $query->whereYear('transaction_date', $year))
            ->when($search, fn ($query) => $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForUserOrFail(int $userId, int $transactionId): Transaction
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->findOrFail($transactionId);
    }

    /**
     * @param  array{category_id: int, type: string, title: string, amount: mixed, description?: ?string, transaction_date: string}  $data
     */
    public function createForUser(int $userId, array $data): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'transaction_date' => $data['transaction_date'],
        ]);
    }

    /**
     * @param  array{category_id: int, type: string, title: string, amount: mixed, description?: ?string, transaction_date: string}  $data
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update([
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'transaction_date' => $data['transaction_date'],
        ]);

        return $transaction->refresh();
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }

    /**
     * @return array<int, int>
     */
    public function getDistinctYears(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM transaction_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }
}
