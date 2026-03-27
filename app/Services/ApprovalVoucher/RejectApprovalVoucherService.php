<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Repositories\ApprovalVoucherRepository;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;

class RejectApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
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

        $approvalVoucher = $this->approvalVoucherRepository->markAsRejected(
            $approvalVoucher,
            $data['rejection_reason'],
        );

        $this->activityLogService->logApprovalVoucherRejected($actor, $approvalVoucher);
        $this->approvalVoucherNotificationService->notifyRequesterOfRejection($approvalVoucher);

        return $approvalVoucher;
    }
}
