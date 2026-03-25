<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoStatus;
use App\Models\ApprovalMemo;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class SubmitApprovalMemoService
{
    public function __construct(
        private readonly ApprovalMemoNotificationService $approvalMemoNotificationService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function handle(User $user, ApprovalMemo $approvalMemo): ApprovalMemo
    {
        if (! $approvalMemo->canSubmitRequest($user)) {
            throw ValidationException::withMessages([
                'approval_memo' => 'Only your draft or rejected memo requests can be submitted.',
            ]);
        }

        $approvalMemo->update([
            'status' => ApprovalMemoStatus::PendingApproval->value,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'admin_remarks' => null,
            'rejection_reason' => null,
        ]);

        $approvalMemo = $approvalMemo->refresh();

        $this->activityLogService->logApprovalMemoSubmitted($user, $approvalMemo);
        $this->approvalMemoNotificationService->notifyAdminsOfSubmission($approvalMemo);

        return $approvalMemo;
    }
}
