<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoStatus;
use App\Models\ApprovalMemo;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class RejectApprovalMemoService
{
    public function __construct(
        private readonly ApprovalMemoNotificationService $approvalMemoNotificationService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array{rejection_reason: string}  $data
     */
    public function handle(User $actor, ApprovalMemo $approvalMemo, array $data): ApprovalMemo
    {
        if (! $approvalMemo->canReject($actor)) {
            throw ValidationException::withMessages([
                'approval_memo' => 'Only pending memo requests can be rejected.',
            ]);
        }

        $approvalMemo->update([
            'status' => ApprovalMemoStatus::Rejected->value,
            'rejected_at' => now(),
            'rejection_reason' => trim($data['rejection_reason']),
            'approved_by' => null,
            'approved_at' => null,
            'admin_remarks' => null,
        ]);

        $approvalMemo = $approvalMemo->refresh();

        $this->activityLogService->logApprovalMemoRejected($actor, $approvalMemo);
        $this->approvalMemoNotificationService->notifyRequesterOfRejection($approvalMemo);

        return $approvalMemo;
    }
}
