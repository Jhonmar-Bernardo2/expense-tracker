<?php

namespace App\Repositories;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VoucherRepository
{
    /**
     * @param  array{status?: ?VoucherStatus, type?: ?VoucherType, search?: ?string}  $filters
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
            ])
            ->withCount('attachments')
            ->withSum('items', 'amount')
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, VoucherStatus $status) => $query->where('status', $status->value),
            )
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, VoucherType $type) => $query->where('type', $type->value),
            )
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('voucher_no', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('requestedBy', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForViewerOrFail(User $user, int $voucherId): Voucher
    {
        return $this->visibleQuery($user)
            ->with($this->detailRelations())
            ->findOrFail($voucherId);
    }

    public function findBasicForViewerOrFail(User $user, int $voucherId): Voucher
    {
        return $this->visibleQuery($user)->findOrFail($voucherId);
    }

    public function findAttachmentForVoucherOrFail(Voucher $voucher, int $attachmentId): VoucherAttachment
    {
        return VoucherAttachment::query()
            ->where('voucher_id', $voucher->id)
            ->findOrFail($attachmentId);
    }

    public function formatVoucherNumber(Voucher $voucher): string
    {
        return sprintf(
            'VCH-%s-%05d',
            $voucher->created_at?->format('Y') ?? now()->format('Y'),
            $voucher->id,
        );
    }

    public function createLog(
        Voucher $voucher,
        ?User $user,
        string $action,
        ?VoucherStatus $fromStatus,
        ?VoucherStatus $toStatus,
        ?string $remarks = null,
    ): VoucherLog {
        return $voucher->logs()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'remarks' => $remarks,
        ]);
    }

    public function refreshDetail(Voucher $voucher): Voucher
    {
        return $voucher->fresh($this->detailRelations());
    }

    /**
     * @return Builder<Voucher>
     */
    private function visibleQuery(User $user): Builder
    {
        return Voucher::query()
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->where('department_id', $user->department_id),
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
            'releasedBy:id,name,email',
            'liquidationReviewedBy:id,name,email',
            'items.category:id,name,type',
            'attachments.uploadedBy:id,name,email',
            'logs.user:id,name,email',
            'transactions.category:id,name,type',
        ];
    }
}
