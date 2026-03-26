<?php

namespace App\Repositories;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
                $user->isAdmin() && $departmentId !== null,
                fn (Builder $query) => $query->where('department_id', $departmentId),
            )
            ->with([
                'department:id,name',
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

    public function countPendingFor(User $user): int
    {
        return $this->visibleQuery($user)
            ->where('status', ApprovalVoucherStatus::PendingApproval->value)
            ->count();
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
            'supportingAttachments',
        ];
    }
}
