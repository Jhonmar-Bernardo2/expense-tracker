<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SubmitApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
    ) {
    }

    public function handle(User $user, ApprovalVoucher $approvalVoucher): ApprovalVoucher
    {
        if (! $approvalVoucher->canSubmitRequest($user)) {
            throw ValidationException::withMessages([
                'approval_voucher' => 'Only your draft or rejected requests can be submitted.',
            ]);
        }

        $target = $this->approvalVoucherPayloadService->resolveTargetForApproval($approvalVoucher);
        $this->approvalVoucherPayloadService->assertCanApply($approvalVoucher, $target);

        $approvalVoucher->update([
            'status' => ApprovalVoucherStatus::PendingApproval->value,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'applied_at' => null,
            'rejection_reason' => null,
        ]);

        return $approvalVoucher->refresh();
    }
}
