<?php

namespace App\Repositories;

use App\Enums\ApprovalVoucherAttachmentKind;
use App\Models\ApprovalVoucher;
use App\Models\ApprovalVoucherAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ApprovalVoucherAttachmentRepository
{
    /**
     * @param  list<int>  $attachmentIds
     * @return Collection<int, ApprovalVoucherAttachment>
     */
    public function getSupportingDocumentsForVoucher(
        ApprovalVoucher $approvalVoucher,
        array $attachmentIds,
    ): Collection {
        return $approvalVoucher->attachments()
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->whereKey($attachmentIds)
            ->get();
    }

    /**
     * @param  list<int>  $attachmentIds
     */
    public function deleteByIds(array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        ApprovalVoucherAttachment::query()
            ->whereKey($attachmentIds)
            ->delete();
    }

    /**
     * @param  array{disk: string, path: string, original_name: string, mime_type: string, size_bytes: int}  $data
     */
    public function createForVoucher(
        ApprovalVoucher $approvalVoucher,
        User $user,
        ApprovalVoucherAttachmentKind $kind,
        array $data,
    ): ApprovalVoucherAttachment {
        return $approvalVoucher->attachments()->create([
            'uploaded_by' => $user->id,
            'kind' => $kind->value,
            'original_name' => $data['original_name'],
            'disk' => $data['disk'],
            'path' => $data['path'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
        ]);
    }

    /**
     * @param  list<int>  $removeAttachmentIds
     */
    public function countSupportingDocumentsForVoucher(
        int $approvalVoucherId,
        array $removeAttachmentIds = [],
    ): int {
        return ApprovalVoucherAttachment::query()
            ->where('approval_voucher_id', $approvalVoucherId)
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->when(
                $removeAttachmentIds !== [],
                fn ($query) => $query->whereKeyNot($removeAttachmentIds),
            )
            ->count();
    }

    /**
     * @param  list<int>  $attachmentIds
     */
    public function countMatchingSupportingDocumentsForVoucher(
        int $approvalVoucherId,
        array $attachmentIds,
    ): int {
        return ApprovalVoucherAttachment::query()
            ->where('approval_voucher_id', $approvalVoucherId)
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->whereIn('id', $attachmentIds)
            ->count();
    }

    public function findForVoucherOrFail(ApprovalVoucher $approvalVoucher, int $attachmentId): ApprovalVoucherAttachment
    {
        return $approvalVoucher->attachments()->findOrFail($attachmentId);
    }
}
