<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class SubmitApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
        private readonly \App\Services\ApprovalMemo\ApprovalMemoLinkService $approvalMemoLinkService,
        private readonly ApprovalVoucherAttachmentService $approvalVoucherAttachmentService,
        private readonly ActivityLogService $activityLogService,
        private readonly ApprovalVoucherNotificationService $approvalVoucherNotificationService,
    ) {}

    public function handle(User $user, ApprovalVoucher $approvalVoucher): ApprovalVoucher
    {
        if (! $approvalVoucher->canSubmitRequest($user)) {
            throw ValidationException::withMessages([
                'approval_voucher' => 'Only your draft or rejected requests can be submitted.',
            ]);
        }

        $target = $this->approvalVoucherPayloadService->resolveTargetForApproval($approvalVoucher);
        $this->approvalVoucherPayloadService->assertCanApply($approvalVoucher, $target);

        if ($approvalVoucher->action !== \App\Enums\ApprovalVoucherAction::Delete) {
            if ($approvalVoucher->module === \App\Enums\ApprovalVoucherModule::Budget) {
                $this->approvalMemoLinkService->assertVoucherCanBeSubmitted($approvalVoucher);
            }

            $this->approvalVoucherAttachmentService->assertVoucherHasApprovalMemoPdfAttachment($approvalVoucher);
        }

        $approvalVoucher->update([
            'status' => ApprovalVoucherStatus::PendingApproval->value,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'applied_at' => null,
            'rejection_reason' => null,
        ]);

        $approvalVoucher = $approvalVoucher->refresh();

        $this->activityLogService->logApprovalVoucherSubmitted($user, $approvalVoucher);
        $this->approvalVoucherNotificationService->notifyAdminsOfSubmission($approvalVoucher);

        return $approvalVoucher;
    }
}
