<?php

namespace App\Services\ApprovalMemo;

use App\Enums\UserRole;
use App\Models\ApprovalMemo;
use App\Models\User;
use App\Notifications\ApprovalVoucherAlertNotification;

class ApprovalMemoNotificationService
{
    public function notifyAdminsOfSubmission(ApprovalMemo $approvalMemo): void
    {
        $title = 'Approval memo request submitted';
        $body = sprintf(
            '%s for %s is awaiting approval.',
            $approvalMemo->memo_no,
            $approvalMemo->resolveSubject(),
        );

        User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->each(function (User $admin) use ($approvalMemo, $title, $body): void {
                $admin->notify(new ApprovalVoucherAlertNotification(
                    $title,
                    $body,
                    route('approval-memos.show', $approvalMemo, false),
                    [
                        'approval_memo_id' => $approvalMemo->id,
                        'status' => $approvalMemo->status->value,
                    ],
                ));
            });
    }

    public function notifyRequesterOfApproval(ApprovalMemo $approvalMemo): void
    {
        $approvalMemo->loadMissing('requestedBy');

        if ($approvalMemo->requestedBy === null) {
            return;
        }

        $approvalMemo->requestedBy->notify(new ApprovalVoucherAlertNotification(
            'Approval memo approved',
            sprintf(
                '%s for %s has been approved and is ready to print or save as PDF.',
                $approvalMemo->memo_no,
                $approvalMemo->resolveSubject(),
            ),
            route('approval-memos.show', $approvalMemo, false),
            [
                'approval_memo_id' => $approvalMemo->id,
                'status' => $approvalMemo->status->value,
            ],
        ));
    }

    public function notifyRequesterOfRejection(ApprovalMemo $approvalMemo): void
    {
        $approvalMemo->loadMissing('requestedBy');

        if ($approvalMemo->requestedBy === null) {
            return;
        }

        $approvalMemo->requestedBy->notify(new ApprovalVoucherAlertNotification(
            'Approval memo rejected',
            sprintf(
                '%s for %s was rejected.',
                $approvalMemo->memo_no,
                $approvalMemo->resolveSubject(),
            ),
            route('approval-memos.show', $approvalMemo, false),
            [
                'approval_memo_id' => $approvalMemo->id,
                'status' => $approvalMemo->status->value,
                'rejection_reason' => $approvalMemo->rejection_reason,
            ],
        ));
    }
}
