<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Repositories\ApprovalVoucherRepository;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class SubmitApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
        private readonly ApproveApprovalVoucherService $approveApprovalVoucherService,
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

        $approvalVoucher = $this->approvalVoucherRepository->markAsSubmitted($approvalVoucher);

        $this->activityLogService->logApprovalVoucherSubmitted($user, $approvalVoucher);

        if ($this->shouldAutoApplyTransactionRequest($user, $approvalVoucher)) {
            return $this->approveApprovalVoucherService->applyImmediately(
                $user,
                $approvalVoucher,
                $approvalVoucher->remarks,
            );
        }

        $this->approvalVoucherNotificationService->notifyApproversOfSubmission($approvalVoucher);

        return $approvalVoucher;
    }

    private function shouldAutoApplyTransactionRequest(User $user, ApprovalVoucher $approvalVoucher): bool
    {
        return $user->isFinancialManagement()
            && $approvalVoucher->module === ApprovalVoucherModule::Transaction
            && $approvalVoucher->status === ApprovalVoucherStatus::PendingApproval;
    }
}
