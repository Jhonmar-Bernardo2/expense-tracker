<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoStatus;
use App\Models\ApprovalMemo;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class ApproveApprovalMemoService
{
    public function __construct(
        private readonly ApprovalMemoNotificationService $approvalMemoNotificationService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array{admin_remarks?: ?string}  $data
     */
    public function handle(User $actor, ApprovalMemo $approvalMemo, array $data): ApprovalMemo
    {
        if (! $approvalMemo->canApprove($actor)) {
            throw ValidationException::withMessages([
                'approval_memo' => 'Only pending memo requests can be approved.',
            ]);
        }

        $approvalMemo->loadMissing('requestedBy');

        $approvalMemo->update([
            'status' => ApprovalMemoStatus::Approved->value,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
            'admin_remarks' => $data['admin_remarks'] ?? null,
        ]);

        $approvalMemo = $approvalMemo->refresh();

        $this->activityLogService->logApprovalMemoApproved($actor, $approvalMemo);
        $this->approvalMemoNotificationService->notifyRequesterOfApproval($approvalMemo);

        return $approvalMemo;
    }
}
