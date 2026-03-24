<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionRepository
{
    /**
     * @param  array{type?: ?TransactionType, category_id?: ?int, month?: ?int, year?: ?int, search?: ?string}  $filters
     */
    public function getForIndex(?int $departmentId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        return $this->activeQuery($departmentId)
            ->with(['category', 'department'])
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

    public function findForViewerOrFail(User $user, int $transactionId): Transaction
    {
        return Transaction::query()
            ->active()
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->where('department_id', $user->department_id),
            )
            ->findOrFail($transactionId);
    }

    /**
     * @param  array{category_id: int, type: string, title: string, amount: mixed, description?: ?string, transaction_date: string}  $data
     */
    public function create(
        User $user,
        int $departmentId,
        array $data,
        ?int $voucherId = null,
        ?int $originApprovalVoucherId = null,
    ): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'voucher_id' => $voucherId,
            'origin_approval_voucher_id' => $originApprovalVoucherId,
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
            'department_id' => $data['department_id'],
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

    public function void(Transaction $transaction, int $approvalVoucherId): Transaction
    {
        $transaction->update([
            'voided_at' => now(),
            'voided_by_approval_voucher_id' => $approvalVoucherId,
        ]);

        return $transaction->refresh();
    }

    /**
     * @return array<int, int>
     */
    public function getDistinctYears(?int $departmentId): array
    {
        return $this->activeQuery($departmentId)
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM transaction_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }

    /**
     * @return Builder<Transaction>
     */
    private function activeQuery(?int $departmentId): Builder
    {
        return Transaction::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId));
    }
}
