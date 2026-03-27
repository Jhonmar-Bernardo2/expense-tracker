<?php

namespace App\Services\ApprovalVoucher;

use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Notifications\ApprovalVoucherAlertNotification;
use App\Repositories\UserRepository;

class ApprovalVoucherNotificationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function notifyApproversOfSubmission(ApprovalVoucher $approvalVoucher): void
    {
        $title = 'Approval request submitted';
        $body = sprintf(
            '%s for %s is awaiting approval.',
            $approvalVoucher->voucher_no,
            $approvalVoucher->resolveSubject(),
        );

        $this->userRepository
            ->getActiveApproversForSubmission($approvalVoucher)
            ->each(function (User $approver) use ($approvalVoucher, $title, $body): void {
                $approver->notify(new ApprovalVoucherAlertNotification(
                    $title,
                    $body,
                    route('app.approval-vouchers.show', $approvalVoucher, false),
                    [
                        'approval_voucher_id' => $approvalVoucher->id,
                        'status' => $approvalVoucher->status->value,
                    ],
                ));
            });
    }

    public function notifyRequesterOfApproval(ApprovalVoucher $approvalVoucher): void
    {
        $approvalVoucher->loadMissing('requestedBy');

        if ($approvalVoucher->requestedBy === null) {
            return;
        }

        $approvalVoucher->requestedBy->notify(new ApprovalVoucherAlertNotification(
            'Approval request approved',
            sprintf(
                '%s for %s has been approved and applied.',
                $approvalVoucher->voucher_no,
                $approvalVoucher->resolveSubject(),
            ),
            route('app.approval-vouchers.show', $approvalVoucher, false),
            [
                'approval_voucher_id' => $approvalVoucher->id,
                'status' => $approvalVoucher->status->value,
            ],
        ));
    }

    public function notifyRequesterOfRejection(ApprovalVoucher $approvalVoucher): void
    {
        $approvalVoucher->loadMissing('requestedBy');

        if ($approvalVoucher->requestedBy === null) {
            return;
        }

        $approvalVoucher->requestedBy->notify(new ApprovalVoucherAlertNotification(
            'Approval request rejected',
            sprintf(
                '%s for %s was rejected.',
                $approvalVoucher->voucher_no,
                $approvalVoucher->resolveSubject(),
            ),
            route('app.approval-vouchers.show', $approvalVoucher, false),
            [
                'approval_voucher_id' => $approvalVoucher->id,
                'status' => $approvalVoucher->status->value,
                'rejection_reason' => $approvalVoucher->rejection_reason,
            ],
        ));
    }
}
