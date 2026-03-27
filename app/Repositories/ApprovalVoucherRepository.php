<?php

namespace App\Repositories;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ApprovalVoucherRepository
{
    /**
     * @param  array{status?: ?ApprovalVoucherStatus, module?: ?ApprovalVoucherModule, action?: ?ApprovalVoucherAction, search?: ?string}  $filters
     */
    public function getForIndex(User $user, ?int $departmentId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        return $this->visibleQuery($user)
            ->when(
                ($user->isAdmin() || $user->isFinancialManagement()) && $departmentId !== null,
                fn (Builder $query) => $query->where('department_id', $departmentId),
            )
            ->with([
                'department:id,name,is_financial_management,is_locked',
                'requestedBy:id,name,email',
                'approvedBy:id,name,email',
            ])
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, ApprovalVoucherStatus $status) => $query->where('status', $status->value),
            )
            ->when(
                $filters['module'] ?? null,
                fn (Builder $query, ApprovalVoucherModule $module) => $query->where('module', $module->value),
            )
            ->when(
                $filters['action'] ?? null,
                fn (Builder $query, ApprovalVoucherAction $action) => $query->where('action', $action->value),
            )
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('voucher_no', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhere('rejection_reason', 'like', "%{$search}%")
                        ->orWhereHas('requestedBy', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw(sprintf(
                "CASE WHEN status = '%s' THEN 0 ELSE 1 END",
                ApprovalVoucherStatus::PendingApproval->value,
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForViewerOrFail(User $user, int $approvalVoucherId): ApprovalVoucher
    {
        return $this->visibleQuery($user)
            ->with($this->detailRelations())
            ->findOrFail($approvalVoucherId);
    }

    public function findBasicForViewerOrFail(User $user, int $approvalVoucherId): ApprovalVoucher
    {
        return $this->visibleQuery($user)->findOrFail($approvalVoucherId);
    }

    public function refreshDetail(ApprovalVoucher $approvalVoucher): ApprovalVoucher
    {
        return $approvalVoucher->fresh($this->detailRelations());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ApprovalVoucher
    {
        return ApprovalVoucher::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRecord(ApprovalVoucher $approvalVoucher, array $attributes): ApprovalVoucher
    {
        $approvalVoucher->update($attributes);

        return $approvalVoucher->refresh();
    }

    public function markAsSubmitted(ApprovalVoucher $approvalVoucher): ApprovalVoucher
    {
        return $this->updateRecord($approvalVoucher, [
            'status' => ApprovalVoucherStatus::PendingApproval->value,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'applied_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function markAsRejected(ApprovalVoucher $approvalVoucher, string $rejectionReason): ApprovalVoucher
    {
        return $this->updateRecord($approvalVoucher, [
            'status' => ApprovalVoucherStatus::Rejected->value,
            'rejected_at' => now(),
            'rejection_reason' => trim($rejectionReason),
            'approved_by' => null,
            'approved_at' => null,
            'applied_at' => null,
        ]);
    }

    public function markAsApproved(
        ApprovalVoucher $approvalVoucher,
        User $actor,
        int $targetId,
        ?string $remarks = null,
    ): ApprovalVoucher {
        return $this->updateRecord($approvalVoucher, [
            'status' => ApprovalVoucherStatus::Approved->value,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'applied_at' => now(),
            'target_id' => $targetId,
            'remarks' => $remarks ?? $approvalVoucher->remarks,
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);
    }

    public function countPendingFor(User $user): int
    {
        $query = $this->visibleQuery($user)
            ->where('status', ApprovalVoucherStatus::PendingApproval->value)
            ->where('requested_by', '!=', $user->id);

        if ($user->isAdmin()) {
            return $query
                ->whereIn('module', [
                    ApprovalVoucherModule::Budget->value,
                    ApprovalVoucherModule::Allocation->value,
                ])
                ->count();
        }

        if ($user->isFinancialManagement()) {
            return $query
                ->where('module', ApprovalVoucherModule::Transaction->value)
                ->count();
        }

        return 0;
    }

    public function countPendingForModule(User $user, ApprovalVoucherModule $module): int
    {
        return $this->visibleQuery($user)
            ->where('status', ApprovalVoucherStatus::PendingApproval->value)
            ->where('requested_by', '!=', $user->id)
            ->where('module', $module->value)
            ->count();
    }

    /**
     * @return Collection<int, ApprovalVoucher>
     */
    public function getRecentByModuleForDashboard(
        User $user,
        ApprovalVoucherModule $module,
        int $limit = 5,
        ?ApprovalVoucherStatus $status = null,
        bool $excludeRequester = false,
    ): Collection {
        return $this->visibleQuery($user)
            ->with($this->summaryRelations())
            ->where('module', $module->value)
            ->when(
                $status !== null,
                fn (Builder $query) => $query->where('status', $status->value),
            )
            ->when(
                $excludeRequester,
                fn (Builder $query) => $query->where('requested_by', '!=', $user->id),
            )
            ->orderByRaw(sprintf(
                "CASE WHEN status = '%s' THEN 0 ELSE 1 END",
                ApprovalVoucherStatus::PendingApproval->value,
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ApprovalVoucher>
     */
    public function getRecentRequestsByRequester(User $user, int $limit = 5): Collection
    {
        return ApprovalVoucher::query()
            ->with($this->summaryRelations())
            ->where('requested_by', $user->id)
            ->orderByRaw(sprintf(
                "CASE WHEN status = '%s' THEN 0 ELSE 1 END",
                ApprovalVoucherStatus::PendingApproval->value,
            ))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{pending: int, approved_this_month: int, rejected_this_month: int}
     */
    public function getRequesterDashboardCounts(User $user, CarbonImmutable $date): array
    {
        $pending = ApprovalVoucher::query()
            ->where('requested_by', $user->id)
            ->where('status', ApprovalVoucherStatus::PendingApproval->value)
            ->count();

        $approvedThisMonth = ApprovalVoucher::query()
            ->where('requested_by', $user->id)
            ->where('status', ApprovalVoucherStatus::Approved->value)
            ->whereMonth('approved_at', $date->month)
            ->whereYear('approved_at', $date->year)
            ->count();

        $rejectedThisMonth = ApprovalVoucher::query()
            ->where('requested_by', $user->id)
            ->where('status', ApprovalVoucherStatus::Rejected->value)
            ->whereMonth('rejected_at', $date->month)
            ->whereYear('rejected_at', $date->year)
            ->count();

        return [
            'pending' => $pending,
            'approved_this_month' => $approvedThisMonth,
            'rejected_this_month' => $rejectedThisMonth,
        ];
    }

    public function formatVoucherNumber(ApprovalVoucher $approvalVoucher): string
    {
        return sprintf(
            'AV-%s-%05d',
            $approvalVoucher->created_at?->format('Y') ?? now()->format('Y'),
            $approvalVoucher->id,
        );
    }

    /**
     * @return Builder<ApprovalVoucher>
     */
    private function visibleQuery(User $user): Builder
    {
        return ApprovalVoucher::query()
            ->when(
                ! $user->isAdmin(),
                function (Builder $query) use ($user): void {
                    $query->where(function (Builder $scopeQuery) use ($user): void {
                        $scopeQuery->where('requested_by', $user->id);

                        if (! $user->isFinancialManagement()) {
                            return;
                        }

                        $scopeQuery
                            ->orWhere('module', ApprovalVoucherModule::Transaction->value)
                            ->orWhere(function (Builder $centralBudgetQuery) use ($user): void {
                                $centralBudgetQuery
                                    ->whereIn('module', [
                                        ApprovalVoucherModule::Budget->value,
                                        ApprovalVoucherModule::Allocation->value,
                                    ])
                                    ->where('department_id', $user->department_id);
                            });
                    });
                },
            );
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'department:id,name,is_financial_management,is_locked',
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
            'supportingAttachments',
        ];
    }

    /**
     * @return list<string>
     */
    private function summaryRelations(): array
    {
        return [
            'department:id,name,is_financial_management,is_locked',
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
        ];
    }
}
