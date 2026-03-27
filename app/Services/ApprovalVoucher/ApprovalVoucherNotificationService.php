<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherModule;
use App\Enums\UserRole;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Notifications\ApprovalVoucherAlertNotification;

class ApprovalVoucherNotificationService
{
    public function notifyApproversOfSubmission(ApprovalVoucher $approvalVoucher): void
    {
        $title = 'Approval request submitted';
        $body = sprintf(
            '%s for %s is awaiting approval.',
            $approvalVoucher->voucher_no,
            $approvalVoucher->resolveSubject(),
        );

        $this->submissionApproverQuery($approvalVoucher)
            ->each(function (User $approver) use ($approvalVoucher, $title, $body): void {
                $approver->notify(new ApprovalVoucherAlertNotification(
                    $title,
                    $body,
                    route('approval-vouchers.show', $approvalVoucher, false),
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
            route('approval-vouchers.show', $approvalVoucher, false),
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
            route('approval-vouchers.show', $approvalVoucher, false),
            [
                'approval_voucher_id' => $approvalVoucher->id,
                'status' => $approvalVoucher->status->value,
                'rejection_reason' => $approvalVoucher->rejection_reason,
            ],
        ));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function submissionApproverQuery(ApprovalVoucher $approvalVoucher)
    {
        return User::query()
            ->where('is_active', true)
            ->when(
                in_array($approvalVoucher->module, [ApprovalVoucherModule::Allocation, ApprovalVoucherModule::Budget], true),
                fn ($query) => $query->where('role', UserRole::Admin->value),
                fn ($query) => $query
                    ->where('role', '!=', UserRole::Admin->value)
                    ->whereHas(
                        'department',
                        fn ($departmentQuery) => $departmentQuery->where('is_financial_management', true),
                    ),
            );
    }
}
