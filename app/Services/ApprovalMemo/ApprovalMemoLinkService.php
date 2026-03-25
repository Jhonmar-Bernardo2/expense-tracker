<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalMemo;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Repositories\ApprovalMemoRepository;
use Illuminate\Validation\ValidationException;

class ApprovalMemoLinkService
{
    public function __construct(
        private readonly ApprovalMemoRepository $approvalMemoRepository,
    ) {}

    public function resolveForDraft(
        User $user,
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        int $departmentId,
        ?int $approvalMemoId,
        ?ApprovalVoucher $currentApprovalVoucher = null,
    ): ?ApprovalMemo {
        if ($action === ApprovalVoucherAction::Delete) {
            if ($approvalMemoId !== null) {
                throw ValidationException::withMessages([
                    'approval_memo_id' => 'Approval memo is only used for create and update requests.',
                ]);
            }

            return null;
        }

        if ($approvalMemoId === null) {
            return null;
        }

        $approvalMemo = $this->approvalMemoRepository->findApprovedEligibleForVoucherOrFail(
            $user,
            $approvalMemoId,
            $module,
            ApprovalMemoAction::from($action->value),
            $departmentId,
            $currentApprovalVoucher?->id,
        );

        if ($approvalMemo === null) {
            throw ValidationException::withMessages([
                'approval_memo_id' => 'Please select an approved memo that matches this request.',
            ]);
        }

        return $approvalMemo;
    }

    public function assertVoucherCanBeSubmitted(ApprovalVoucher $approvalVoucher): ApprovalMemo
    {
        if ($approvalVoucher->action === ApprovalVoucherAction::Delete) {
            throw ValidationException::withMessages([
                'approval_memo_id' => 'Delete requests do not require an approval memo.',
            ]);
        }

        $approvalVoucher->loadMissing('approvalMemo.linkedApprovalVoucher');

        $approvalMemo = $approvalVoucher->approvalMemo;

        if ($approvalMemo === null) {
            throw ValidationException::withMessages([
                'approval_memo_id' => 'An approved memo is required before submitting this request.',
            ]);
        }

        if (
            $approvalMemo->requested_by !== $approvalVoucher->requested_by
            || $approvalMemo->department_id !== $approvalVoucher->department_id
            || $approvalMemo->module !== $approvalVoucher->module
            || $approvalMemo->action->value !== $approvalVoucher->action->value
            || $approvalMemo->status !== ApprovalMemoStatus::Approved
            || $approvalMemo->linkedApprovalVoucher?->id !== $approvalVoucher->id
        ) {
            throw ValidationException::withMessages([
                'approval_memo_id' => 'The linked approval memo is no longer valid for this request.',
            ]);
        }

        return $approvalMemo;
    }
}
