<?php

namespace App\Services;

use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\ActivityLogRepository;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository,
    ) {}

    public function logApprovalVoucherCreated(User $actor, ApprovalVoucher $approvalVoucher): void
    {
        $this->activityLogRepository->createForApprovalVoucher(
            $approvalVoucher,
            $actor,
            'approval_voucher.created',
            'Request created.',
            $this->baseMeta($approvalVoucher),
        );
    }

    public function logApprovalVoucherUpdated(User $actor, ApprovalVoucher $approvalVoucher): void
    {
        $this->activityLogRepository->createForApprovalVoucher(
            $approvalVoucher,
            $actor,
            'approval_voucher.updated',
            'Request updated.',
            $this->baseMeta($approvalVoucher),
        );
    }

    public function logApprovalVoucherSubmitted(User $actor, ApprovalVoucher $approvalVoucher): void
    {
        $this->activityLogRepository->createForApprovalVoucher(
            $approvalVoucher,
            $actor,
            'approval_voucher.submitted',
            'Request submitted for approval.',
            $this->baseMeta($approvalVoucher),
        );
    }

    public function logApprovalVoucherApproved(User $actor, ApprovalVoucher $approvalVoucher): void
    {
        $this->activityLogRepository->createForApprovalVoucher(
            $approvalVoucher,
            $actor,
            'approval_voucher.approved',
            'Request approved and applied.',
            $this->baseMeta($approvalVoucher),
        );
    }

    public function logApprovalVoucherRejected(User $actor, ApprovalVoucher $approvalVoucher): void
    {
        $this->activityLogRepository->createForApprovalVoucher(
            $approvalVoucher,
            $actor,
            'approval_voucher.rejected',
            'Request rejected.',
            array_merge($this->baseMeta($approvalVoucher), [
                'rejection_reason' => $approvalVoucher->rejection_reason,
            ]),
        );
    }

    public function logAppliedChange(
        User $actor,
        ApprovalVoucher $approvalVoucher,
        Transaction|Budget $record,
    ): void {
        [$event, $summary] = match (true) {
            $record instanceof Transaction && $approvalVoucher->action->value === 'delete' => [
                'transaction.voided_from_voucher',
                'Transaction voided from approved request.',
            ],
            $record instanceof Transaction => [
                'transaction.applied_from_voucher',
                'Transaction change applied from approved request.',
            ],
            $approvalVoucher->action->value === 'delete' => [
                'budget.archived_from_voucher',
                'Budget archived from approved request.',
            ],
            default => [
                'budget.applied_from_voucher',
                'Budget change applied from approved request.',
            ],
        };

        $this->activityLogRepository->createForApprovalVoucher(
            $approvalVoucher,
            $actor,
            $event,
            $summary,
            array_merge($this->baseMeta($approvalVoucher), [
                'target_type' => $record->getMorphClass(),
                'target_id' => $record->getKey(),
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(ApprovalVoucher $approvalVoucher): array
    {
        return [
            'voucher_no' => $approvalVoucher->voucher_no,
            'module' => $approvalVoucher->module->value,
            'action' => $approvalVoucher->action->value,
            'status' => $approvalVoucher->status->value,
            'target_id' => $approvalVoucher->target_id,
        ];
    }
}
