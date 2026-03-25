<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherAttachmentKind;
use App\Models\ApprovalVoucher;
use App\Models\ApprovalVoucherAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalVoucherAttachmentService
{
    private const DISK = 'local';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function syncForVoucher(
        User $user,
        ApprovalVoucher $approvalVoucher,
        array $data,
        array &$storedFiles = [],
    ): void {
        $removeAttachmentIds = collect($data['remove_attachment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($removeAttachmentIds !== []) {
            $this->removeExistingSupportingAttachments($approvalVoucher, $removeAttachmentIds);
        }

        $this->storeNewAttachments(
            $user,
            $approvalVoucher,
            $data['attachments'] ?? [],
            ApprovalVoucherAttachmentKind::SupportingDocument,
            $storedFiles,
        );

        if (($data['remove_approval_memo_pdf'] ?? false) === true) {
            $this->clearApprovalMemoPdfAttachment($approvalVoucher);
        }

        if (($data['approval_memo_pdf'] ?? null) instanceof UploadedFile) {
            $this->replaceApprovalMemoPdfAttachment(
                $user,
                $approvalVoucher,
                $data['approval_memo_pdf'],
                $storedFiles,
            );
        }
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function cleanupStoredFiles(array $storedFiles): void
    {
        $this->deleteStoredFiles($storedFiles);
    }

    public function clearApprovalMemoPdfAttachment(ApprovalVoucher $approvalVoucher): void
    {
        $attachments = $approvalVoucher->attachments()
            ->where('kind', ApprovalVoucherAttachmentKind::ApprovalMemoPdf->value)
            ->get();

        if ($attachments->isEmpty()) {
            return;
        }

        $filesToDelete = $attachments
            ->map(fn (ApprovalVoucherAttachment $attachment) => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();

        ApprovalVoucherAttachment::query()
            ->whereKey($attachments->modelKeys())
            ->delete();

        DB::afterCommit(fn () => $this->deleteStoredFiles($filesToDelete));
    }

    public function replaceApprovalMemoPdfAttachment(
        User $user,
        ApprovalVoucher $approvalVoucher,
        UploadedFile $upload,
        array &$storedFiles = [],
    ): ApprovalVoucherAttachment {
        $this->clearApprovalMemoPdfAttachment($approvalVoucher);

        return $this->storeAttachment(
            $user,
            $approvalVoucher,
            $upload,
            ApprovalVoucherAttachmentKind::ApprovalMemoPdf,
            $storedFiles,
        );
    }

    public function assertVoucherHasApprovalMemoPdfAttachment(ApprovalVoucher $approvalVoucher): ApprovalVoucherAttachment
    {
        $approvalVoucher->loadMissing('approvalMemoPdfAttachment');

        if ($approvalVoucher->approvalMemoPdfAttachment === null) {
            throw ValidationException::withMessages([
                'approval_memo_pdf' => 'Upload the approval memo PDF before submitting this request.',
            ]);
        }

        return $approvalVoucher->approvalMemoPdfAttachment;
    }

    /**
     * @param  list<int>  $attachmentIds
     */
    private function removeExistingSupportingAttachments(ApprovalVoucher $approvalVoucher, array $attachmentIds): void
    {
        $attachments = $approvalVoucher->attachments()
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->whereKey($attachmentIds)
            ->get();

        if ($attachments->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages([
                'remove_attachment_ids' => 'One or more supporting attachments could not be found for this voucher.',
            ]);
        }

        $filesToDelete = $attachments
            ->map(fn (ApprovalVoucherAttachment $attachment) => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();

        ApprovalVoucherAttachment::query()
            ->whereKey($attachments->modelKeys())
            ->delete();

        DB::afterCommit(fn () => $this->deleteStoredFiles($filesToDelete));
    }

    /**
     * @param  array<int, UploadedFile|mixed>  $uploads
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    private function storeNewAttachments(
        User $user,
        ApprovalVoucher $approvalVoucher,
        array $uploads,
        ApprovalVoucherAttachmentKind $kind,
        array &$storedFiles,
    ): void {
        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $this->storeAttachment($user, $approvalVoucher, $upload, $kind, $storedFiles);
        }
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    private function storeAttachment(
        User $user,
        ApprovalVoucher $approvalVoucher,
        UploadedFile $upload,
        ApprovalVoucherAttachmentKind $kind,
        array &$storedFiles,
    ): ApprovalVoucherAttachment {
        $storedFile = $this->storeUploadedFile($approvalVoucher, $upload, $kind);
        $storedFiles[] = $storedFile;

        return $approvalVoucher->attachments()->create([
            'uploaded_by' => $user->id,
            'kind' => $kind->value,
            'original_name' => $this->normalizeOriginalName($upload),
            'disk' => $storedFile['disk'],
            'path' => $storedFile['path'],
            'mime_type' => $upload->getMimeType()
                ?? $upload->getClientMimeType()
                ?? 'application/octet-stream',
            'size_bytes' => (int) ($upload->getSize() ?? 0),
        ]);
    }

    /**
     * @return array{disk: string, path: string}
     */
    private function storeUploadedFile(
        ApprovalVoucher $approvalVoucher,
        UploadedFile $upload,
        ApprovalVoucherAttachmentKind $kind,
    ): array
    {
        $extension = $upload->extension();
        $filename = (string) Str::ulid();

        if (is_string($extension) && $extension !== '') {
            $filename .= ".{$extension}";
        }

        $folder = $kind === ApprovalVoucherAttachmentKind::ApprovalMemoPdf
            ? 'approval-memo-pdf'
            : 'supporting-documents';

        $path = Storage::disk(self::DISK)->putFileAs(
            "approval-vouchers/{$approvalVoucher->getKey()}/{$folder}",
            $upload,
            $filename,
        );

        if (! is_string($path) || $path === '') {
            $errorField = $kind === ApprovalVoucherAttachmentKind::ApprovalMemoPdf
                ? 'approval_memo_pdf'
                : 'attachments';

            throw ValidationException::withMessages([
                $errorField => 'Unable to store the uploaded attachment.',
            ]);
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
        ];
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    private function deleteStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $storedFile) {
            if (
                ! isset($storedFile['disk'], $storedFile['path'])
                || ! is_string($storedFile['disk'])
                || ! is_string($storedFile['path'])
                || $storedFile['path'] === ''
            ) {
                continue;
            }

            Storage::disk($storedFile['disk'])->delete($storedFile['path']);
        }
    }

    private function normalizeOriginalName(UploadedFile $upload): string
    {
        return Str::of($upload->getClientOriginalName())
            ->limit(255, '')
            ->toString();
    }
}
