<?php

namespace App\Repositories;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApprovalMemoRepository
{
    /**
     * @param  array{status?: ?ApprovalMemoStatus, module?: ?ApprovalVoucherModule, action?: ?ApprovalMemoAction, search?: ?string}  $filters
     */
    public function getForIndex(User $user, ?int $departmentId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        return $this->visibleQuery($user)
            ->when(
                $user->isAdmin() && $departmentId !== null,
                fn (Builder $query) => $query->where('department_id', $departmentId),
            )
            ->with([
                'department:id,name',
                'requestedBy:id,name,email',
                'approvedBy:id,name,email',
                'linkedApprovalVoucher:id,approval_memo_id,voucher_no,status',
            ])
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, ApprovalMemoStatus $status) => $query->where('status', $status->value),
            )
            ->when(
                $filters['module'] ?? null,
                fn (Builder $query, ApprovalVoucherModule $module) => $query->where('module', $module->value),
            )
            ->when(
                $filters['action'] ?? null,
                fn (Builder $query, ApprovalMemoAction $action) => $query->where('action', $action->value),
            )
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('memo_no', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhere('admin_remarks', 'like', "%{$search}%")
                        ->orWhere('rejection_reason', 'like', "%{$search}%")
                        ->orWhereHas('requestedBy', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw(sprintf(
                "CASE WHEN status = '%s' THEN 0 ELSE 1 END",
                ApprovalMemoStatus::PendingApproval->value,
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForViewerOrFail(User $user, int $approvalMemoId): ApprovalMemo
    {
        return $this->visibleQuery($user)
            ->with($this->detailRelations())
            ->findOrFail($approvalMemoId);
    }

    public function findApprovedForViewerOrFail(User $user, int $approvalMemoId): ApprovalMemo
    {
        return $this->visibleQuery($user)
            ->where('status', ApprovalMemoStatus::Approved->value)
            ->with($this->detailRelations())
            ->findOrFail($approvalMemoId);
    }

    public function findBasicForViewerOrFail(User $user, int $approvalMemoId): ApprovalMemo
    {
        return $this->visibleQuery($user)->findOrFail($approvalMemoId);
    }

    public function refreshDetail(ApprovalMemo $approvalMemo): ApprovalMemo
    {
        return $approvalMemo->fresh($this->detailRelations());
    }

    public function countPendingFor(User $user): int
    {
        return $this->visibleQuery($user)
            ->where('status', ApprovalMemoStatus::PendingApproval->value)
            ->count();
    }

    public function formatMemoNumber(ApprovalMemo $approvalMemo): string
    {
        return sprintf(
            'AM-%s-%05d',
            $approvalMemo->created_at?->format('Y') ?? now()->format('Y'),
            $approvalMemo->id,
        );
    }

    /**
     * @return Collection<int, ApprovalMemo>
     */
    public function getEligibleApprovedForUser(User $user, ?int $currentApprovalVoucherId = null): Collection
    {
        return ApprovalMemo::query()
            ->where('requested_by', $user->id)
            ->where('status', ApprovalMemoStatus::Approved->value)
            ->where(function (Builder $query) use ($currentApprovalVoucherId): void {
                $query->whereDoesntHave('linkedApprovalVoucher');

                if ($currentApprovalVoucherId !== null) {
                    $query->orWhereHas(
                        'linkedApprovalVoucher',
                        fn (Builder $voucherQuery) => $voucherQuery->whereKey($currentApprovalVoucherId),
                    );
                }
            })
            ->with([
                'department:id,name',
                'linkedApprovalVoucher:id,approval_memo_id',
            ])
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findApprovedEligibleForVoucherOrFail(
        User $user,
        int $approvalMemoId,
        ApprovalVoucherModule $module,
        ApprovalMemoAction $action,
        int $departmentId,
        ?int $currentApprovalVoucherId = null,
    ): ?ApprovalMemo {
        return ApprovalMemo::query()
            ->whereKey($approvalMemoId)
            ->where('requested_by', $user->id)
            ->where('department_id', $departmentId)
            ->where('module', $module->value)
            ->where('action', $action->value)
            ->where('status', ApprovalMemoStatus::Approved->value)
            ->where(function (Builder $query) use ($currentApprovalVoucherId): void {
                $query->whereDoesntHave('linkedApprovalVoucher');

                if ($currentApprovalVoucherId !== null) {
                    $query->orWhereHas(
                        'linkedApprovalVoucher',
                        fn (Builder $voucherQuery) => $voucherQuery->whereKey($currentApprovalVoucherId),
                    );
                }
            })
            ->first();
    }

    /**
     * @return Builder<ApprovalMemo>
     */
    private function visibleQuery(User $user): Builder
    {
        return ApprovalMemo::query()
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->where('requested_by', $user->id),
            );
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'department:id,name',
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
            'requestSupportAttachments',
            'linkedApprovalVoucher:id,approval_memo_id,voucher_no,status',
        ];
    }
}
