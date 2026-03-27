<?php

namespace App\Repositories;

use App\Models\BudgetAllocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BudgetAllocationRepository
{
    public function getActiveForPeriod(int $departmentId, int $month, int $year): ?BudgetAllocation
    {
        return BudgetAllocation::query()
            ->active()
            ->where('department_id', $departmentId)
            ->where('month', $month)
            ->where('year', $year)
            ->with('department')
            ->first();
    }

    /**
     * @return Collection<int, BudgetAllocation>
     */
    public function getAccessible(?int $departmentId): Collection
    {
        return BudgetAllocation::query()
            ->active()
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->with('department')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->get();
    }

    public function findForViewerOrFail(User $user, int $allocationId): BudgetAllocation
    {
        return BudgetAllocation::query()
            ->active()
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->where(
                    'department_id',
                    $user->isFinancialManagement() ? $user->department_id : 0,
                ),
            )
            ->findOrFail($allocationId);
    }

    /**
     * @param  array{month: int, year: int, amount_limit: mixed}  $data
     */
    public function create(
        User $user,
        int $departmentId,
        array $data,
        ?int $originApprovalVoucherId = null,
    ): BudgetAllocation {
        return BudgetAllocation::query()->create([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'origin_approval_voucher_id' => $originApprovalVoucherId,
            'month' => $data['month'],
            'year' => $data['year'],
            'amount_limit' => $data['amount_limit'],
        ]);
    }

    /**
     * @param  array{department_id: int, month: int, year: int, amount_limit: mixed}  $data
     */
    public function update(BudgetAllocation $budgetAllocation, array $data): BudgetAllocation
    {
        $budgetAllocation->update([
            'department_id' => $data['department_id'],
            'month' => $data['month'],
            'year' => $data['year'],
            'amount_limit' => $data['amount_limit'],
        ]);

        return $budgetAllocation->refresh();
    }

    public function archive(BudgetAllocation $budgetAllocation, ?int $approvalVoucherId = null): BudgetAllocation
    {
        $budgetAllocation->update([
            'archived_at' => now(),
            'archived_by_approval_voucher_id' => $approvalVoucherId,
        ]);

        return $budgetAllocation->refresh();
    }

    public function existsActiveConflict(
        int $departmentId,
        int $month,
        int $year,
        ?int $ignoreAllocationId = null,
    ): bool {
        return BudgetAllocation::query()
            ->active()
            ->where('department_id', $departmentId)
            ->where('month', $month)
            ->where('year', $year)
            ->when($ignoreAllocationId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreAllocationId))
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    public function getYearOptions(?int $departmentId, int $currentYear): array
    {
        return collect(range($currentYear - 1, $currentYear + 5))
            ->merge(
                BudgetAllocation::query()
                    ->active()
                    ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
                    ->pluck('year')
                    ->map(fn ($year) => (int) $year),
            )
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
