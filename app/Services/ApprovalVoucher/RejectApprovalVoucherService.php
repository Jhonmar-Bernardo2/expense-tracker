<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class RejectApprovalVoucherService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly ApprovalVoucherNotificationService $approvalVoucherNotificationService,
    ) {}

    /**
     * @param  array{rejection_reason: string}  $data
     */
    public function handle(User $actor, ApprovalVoucher $approvalVoucher, array $data): ApprovalVoucher
    {
        if (! $approvalVoucher->canReject($actor)) {
            throw ValidationException::withMessages([
                'approval_voucher' => 'Only pending requests can be rejected.',
            ]);
        }

        $approvalVoucher->update([
            'status' => ApprovalVoucherStatus::Rejected->value,
            'rejected_at' => now(),
            'rejection_reason' => trim($data['rejection_reason']),
            'approved_by' => null,
            'approved_at' => null,
            'applied_at' => null,
        ]);

        $approvalVoucher = $approvalVoucher->refresh();

        $this->activityLogService->logApprovalVoucherRejected($actor, $approvalVoucher);
        $this->approvalVoucherNotificationService->notifyRequesterOfRejection($approvalVoucher);

        return $approvalVoucher;
    }
}
