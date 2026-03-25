<?php

namespace App\Services\ApprovalMemo;

use App\Models\ApprovalMemo;
use App\Models\ApprovalMemoAttachment;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteApprovalMemoService
{
    public function __construct(
        private readonly ApprovalMemoAttachmentService $approvalMemoAttachmentService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function handle(User $user, ApprovalMemo $approvalMemo): void
    {
        $approvalMemo->loadMissing('linkedApprovalVoucher');

        if (! $approvalMemo->canDeleteRequest($user)) {
            throw ValidationException::withMessages([
                'approval_memo' => 'Only your unlinked draft or rejected memo requests can be deleted.',
            ]);
        }

        DB::transaction(function () use ($user, $approvalMemo): void {
            $storedFiles = $approvalMemo->attachments()
                ->get()
                ->map(fn (ApprovalMemoAttachment $attachment) => [
                    'disk' => $attachment->disk,
                    'path' => $attachment->path,
                ])
                ->all();

            $this->activityLogService->logApprovalMemoDeleted($user, $approvalMemo);

            $approvalMemo->delete();

            DB::afterCommit(fn () => $this->approvalMemoAttachmentService->cleanupStoredFiles($storedFiles));
        });
    }
}
