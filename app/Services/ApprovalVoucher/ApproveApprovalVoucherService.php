<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
        private readonly TransactionRepository $transactionRepository,
        private readonly BudgetRepository $budgetRepository,
    ) {
    }

    /**
     * @param  array{remarks?: ?string}  $data
     */
    public function handle(User $actor, ApprovalVoucher $approvalVoucher, array $data): ApprovalVoucher
    {
        if (! $approvalVoucher->canApprove($actor)) {
            throw ValidationException::withMessages([
                'approval_voucher' => 'Only pending requests can be approved.',
            ]);
        }

        return DB::transaction(function () use ($actor, $approvalVoucher, $data): ApprovalVoucher {
            $approvalVoucher->loadMissing('requestedBy');

            $target = $this->approvalVoucherPayloadService->resolveTargetForApproval($approvalVoucher);
            $this->approvalVoucherPayloadService->assertCanApply($approvalVoucher, $target);

            $appliedTargetId = $this->applyChange($approvalVoucher, $target);

            $approvalVoucher->update([
                'status' => ApprovalVoucherStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'applied_at' => now(),
                'target_id' => $appliedTargetId,
                'remarks' => $data['remarks'] ?? $approvalVoucher->remarks,
                'rejection_reason' => null,
                'rejected_at' => null,
            ]);

            return $approvalVoucher->refresh();
        });
    }

    /**
     * @param  Transaction|Budget|null  $target
     */
    private function applyChange(ApprovalVoucher $approvalVoucher, Transaction|Budget|null $target): int
    {
        if ($approvalVoucher->module === ApprovalVoucherModule::Transaction) {
            return $this->applyTransactionChange($approvalVoucher, $target instanceof Transaction ? $target : null);
        }

        return $this->applyBudgetChange($approvalVoucher, $target instanceof Budget ? $target : null);
    }

    private function applyTransactionChange(ApprovalVoucher $approvalVoucher, ?Transaction $transaction): int
    {
        /** @var array{department_id: int, category_id: int, type: string, title: string, amount: float|int|string, description?: ?string, transaction_date: string}|null $payload */
        $payload = $approvalVoucher->after_payload;

        return match ($approvalVoucher->action) {
            ApprovalVoucherAction::Create => $this->transactionRepository->create(
                $approvalVoucher->requestedBy,
                (int) $payload['department_id'],
                $payload,
                null,
                $approvalVoucher->id,
            )->id,
            ApprovalVoucherAction::Update => $this->transactionRepository->update(
                $this->requireTransactionTarget($transaction),
                $payload,
            )->id,
            ApprovalVoucherAction::Delete => $this->transactionRepository->void(
                $this->requireTransactionTarget($transaction),
                $approvalVoucher->id,
            )->id,
        };
    }

    private function applyBudgetChange(ApprovalVoucher $approvalVoucher, ?Budget $budget): int
    {
        /** @var array{department_id: int, category_id: int, month: int, year: int, amount_limit: float|int|string}|null $payload */
        $payload = $approvalVoucher->after_payload;

        return match ($approvalVoucher->action) {
            ApprovalVoucherAction::Create => $this->budgetRepository->create(
                $approvalVoucher->requestedBy,
                (int) $payload['department_id'],
                $payload,
                $approvalVoucher->id,
            )->id,
            ApprovalVoucherAction::Update => $this->budgetRepository->update(
                $this->requireBudgetTarget($budget),
                $payload,
            )->id,
            ApprovalVoucherAction::Delete => $this->budgetRepository->archive(
                $this->requireBudgetTarget($budget),
                $approvalVoucher->id,
            )->id,
        };
    }

    private function requireTransactionTarget(?Transaction $transaction): Transaction
    {
        if ($transaction !== null) {
            return $transaction;
        }

        throw ValidationException::withMessages([
            'approval_voucher' => 'The selected transaction is no longer active.',
        ]);
    }

    private function requireBudgetTarget(?Budget $budget): Budget
    {
        if ($budget !== null) {
            return $budget;
        }

        throw ValidationException::withMessages([
            'approval_voucher' => 'The selected budget is no longer active.',
        ]);
    }
}
