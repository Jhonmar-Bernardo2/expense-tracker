<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoAttachmentKind;
use App\Models\ApprovalMemo;
use App\Models\ApprovalMemoAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalMemoAttachmentService
{
    private const DISK = 'local';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function syncRequestSupportForMemo(
        User $user,
        ApprovalMemo $approvalMemo,
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
            $this->removeExistingRequestSupportAttachments($approvalMemo, $removeAttachmentIds);
        }

        $this->storeNewAttachments(
            $user,
            $approvalMemo,
            $data['attachments'] ?? [],
            ApprovalMemoAttachmentKind::RequestSupport,
            $storedFiles,
        );
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function storeApprovedMemo(
        User $user,
        ApprovalMemo $approvalMemo,
        UploadedFile $upload,
        array &$storedFiles = [],
    ): ApprovalMemoAttachment {
        $this->removeExistingApprovedMemoAttachments($approvalMemo);

        return $this->storeAttachment(
            $user,
            $approvalMemo,
            $upload,
            ApprovalMemoAttachmentKind::ApprovedMemo,
            $storedFiles,
        );
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function cleanupStoredFiles(array $storedFiles): void
    {
        $this->deleteStoredFiles($storedFiles);
    }

    /**
     * @param  list<int>  $attachmentIds
     */
    private function removeExistingRequestSupportAttachments(ApprovalMemo $approvalMemo, array $attachmentIds): void
    {
        $attachments = $approvalMemo->attachments()
            ->where('kind', ApprovalMemoAttachmentKind::RequestSupport->value)
            ->whereKey($attachmentIds)
            ->get();

        if ($attachments->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages([
                'remove_attachment_ids' => 'One or more request support files could not be found for this memo.',
            ]);
        }

        $filesToDelete = $attachments
            ->map(fn (ApprovalMemoAttachment $attachment) => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();

        ApprovalMemoAttachment::query()
            ->whereKey($attachments->modelKeys())
            ->delete();

        DB::afterCommit(fn () => $this->deleteStoredFiles($filesToDelete));
    }

    private function removeExistingApprovedMemoAttachments(ApprovalMemo $approvalMemo): void
    {
        $attachments = $approvalMemo->attachments()
            ->where('kind', ApprovalMemoAttachmentKind::ApprovedMemo->value)
            ->get();

        if ($attachments->isEmpty()) {
            return;
        }

        $filesToDelete = $attachments
            ->map(fn (ApprovalMemoAttachment $attachment) => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();

        ApprovalMemoAttachment::query()
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
        ApprovalMemo $approvalMemo,
        array $uploads,
        ApprovalMemoAttachmentKind $kind,
        array &$storedFiles,
    ): void {
        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $this->storeAttachment($user, $approvalMemo, $upload, $kind, $storedFiles);
        }
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    private function storeAttachment(
        User $user,
        ApprovalMemo $approvalMemo,
        UploadedFile $upload,
        ApprovalMemoAttachmentKind $kind,
        array &$storedFiles,
    ): ApprovalMemoAttachment {
        $storedFile = $this->storeUploadedFile($approvalMemo, $upload);
        $storedFiles[] = $storedFile;

        return $approvalMemo->attachments()->create([
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
    private function storeUploadedFile(ApprovalMemo $approvalMemo, UploadedFile $upload): array
    {
        $extension = $upload->extension();
        $filename = (string) Str::ulid();

        if (is_string($extension) && $extension !== '') {
            $filename .= ".{$extension}";
        }

        $path = Storage::disk(self::DISK)->putFileAs(
            "approval-memos/{$approvalMemo->getKey()}",
            $upload,
            $filename,
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'attachments' => 'Unable to store the uploaded memo attachment.',
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
