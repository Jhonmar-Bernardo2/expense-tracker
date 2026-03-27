<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\Budget\BudgetAllocationSummaryService;
use App\Services\Department\DepartmentScopeService;
use Illuminate\Validation\ValidationException;

class ApprovalVoucherPayloadService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetAllocationRepository $budgetAllocationRepository,
        private readonly BudgetAccessService $budgetAccessService,
        private readonly BudgetAllocationSummaryService $budgetAllocationSummaryService,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     module: ApprovalVoucherModule,
     *     action: ApprovalVoucherAction,
     *     department_id: int,
     *     target_id: int|null,
     *     before_payload: array<string, mixed>|null,
     *     after_payload: array<string, mixed>|null
     * }
     */
    public function buildDraftPayload(User $user, array $data): array
    {
        $module = ApprovalVoucherModule::from((string) $data['module']);
        $action = ApprovalVoucherAction::from((string) $data['action']);
        $this->assertAllocationDeleteIsDisabled($module, $action);
        $target = $this->findRequesterTarget(
            $user,
            $module,
            $action,
            isset($data['target_id']) ? (int) $data['target_id'] : null,
        );
        $departmentId = $this->resolveDepartmentId($user, $module, $action, $target, $data);
        $beforePayload = $this->snapshotTarget($module, $target);
        $afterPayload = $this->buildAfterPayload($module, $action, $departmentId, $data);

        $this->assertBudgetConflict($module, $action, $afterPayload, $target?->id);
        $this->assertAllocationConflict($module, $action, $afterPayload, $target?->id);

        return [
            'module' => $module,
            'action' => $action,
            'department_id' => $departmentId,
            'target_id' => $target?->id,
            'before_payload' => $beforePayload,
            'after_payload' => $afterPayload,
        ];
    }

    /**
     * @return Transaction|Budget|BudgetAllocation|null
     */
    public function resolveTargetForApproval(
        ApprovalVoucher $approvalVoucher,
    ): Transaction|Budget|BudgetAllocation|null {
        return match ($approvalVoucher->action) {
            ApprovalVoucherAction::Create => null,
            default => match ($approvalVoucher->module) {
                ApprovalVoucherModule::Transaction => $this->transactionRepository->findActiveById($approvalVoucher->target_id),
                ApprovalVoucherModule::Budget => $this->budgetRepository->findActiveById($approvalVoucher->target_id),
                ApprovalVoucherModule::Allocation => $this->budgetAllocationRepository->findActiveById($approvalVoucher->target_id),
            },
        };
    }

    public function assertCanApply(
        ApprovalVoucher $approvalVoucher,
        Transaction|Budget|BudgetAllocation|null $target,
    ): void {
        $this->assertAllocationDeleteIsDisabled($approvalVoucher->module, $approvalVoucher->action);

        if ($approvalVoucher->action !== ApprovalVoucherAction::Create && $target === null) {
            $entity = match ($approvalVoucher->module) {
                ApprovalVoucherModule::Transaction => 'transaction',
                ApprovalVoucherModule::Budget => 'budget',
                ApprovalVoucherModule::Allocation => 'allocation',
            };

            throw ValidationException::withMessages([
                'approval_voucher' => "The selected {$entity} is no longer active.",
            ]);
        }

        $ignoreTargetId = $approvalVoucher->action === ApprovalVoucherAction::Update
            ? $approvalVoucher->target_id
            : null;

        $this->assertBudgetConflict(
            $approvalVoucher->module,
            $approvalVoucher->action,
            $approvalVoucher->after_payload,
            $ignoreTargetId,
        );
        $this->assertAllocationConflict(
            $approvalVoucher->module,
            $approvalVoucher->action,
            $approvalVoucher->after_payload,
            $ignoreTargetId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function buildAfterPayload(
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        int $departmentId,
        array $data,
    ): ?array {
        if ($action === ApprovalVoucherAction::Delete) {
            return null;
        }

        return match ($module) {
            ApprovalVoucherModule::Transaction => [
                'department_id' => $departmentId,
                'category_id' => (int) $data['category_id'],
                'type' => (string) $data['type'],
                'title' => trim((string) $data['title']),
                'amount' => round((float) $data['amount'], 2),
                'description' => isset($data['description']) && $data['description'] !== ''
                    ? trim((string) $data['description'])
                    : null,
                'transaction_date' => (string) $data['transaction_date'],
            ],
            ApprovalVoucherModule::Budget => [
                'department_id' => $departmentId,
                'category_id' => (int) $data['category_id'],
                'month' => (int) $data['month'],
                'year' => (int) $data['year'],
                'amount_limit' => round((float) $data['amount_limit'], 2),
            ],
            ApprovalVoucherModule::Allocation => [
                'department_id' => $departmentId,
                'month' => (int) $data['month'],
                'year' => (int) $data['year'],
                'amount_limit' => round((float) $data['amount_limit'], 2),
            ],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotTarget(
        ApprovalVoucherModule $module,
        Transaction|Budget|BudgetAllocation|null $target,
    ): ?array {
        if ($target === null) {
            return null;
        }

        if ($module === ApprovalVoucherModule::Transaction && $target instanceof Transaction) {
            return [
                'department_id' => (int) $target->department_id,
                'category_id' => (int) $target->category_id,
                'type' => $target->type->value,
                'title' => $target->title,
                'amount' => round((float) $target->amount, 2),
                'description' => $target->description,
                'transaction_date' => $target->transaction_date?->toDateString(),
            ];
        }

        if ($target instanceof Budget) {
            return [
                'department_id' => (int) $target->department_id,
                'category_id' => (int) $target->category_id,
                'month' => (int) $target->month,
                'year' => (int) $target->year,
                'amount_limit' => round((float) $target->amount_limit, 2),
            ];
        }

        if ($target instanceof BudgetAllocation) {
            return [
                'department_id' => (int) $target->department_id,
                'month' => (int) $target->month,
                'year' => (int) $target->year,
                'amount_limit' => round((float) $target->amount_limit, 2),
            ];
        }

        return null;
    }

    /**
     * @return Transaction|Budget|BudgetAllocation|null
     */
    private function findRequesterTarget(
        User $user,
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        ?int $targetId,
    ): Transaction|Budget|BudgetAllocation|null {
        if ($action === ApprovalVoucherAction::Create) {
            return null;
        }

        if ($targetId === null) {
            throw ValidationException::withMessages([
                'target_id' => 'Please select a valid record to update or delete.',
            ]);
        }

        return match ($module) {
            ApprovalVoucherModule::Transaction => $this->transactionRepository->findForViewerOrFail($user, $targetId),
            ApprovalVoucherModule::Budget => $this->budgetRepository->findForViewerOrFail($user, $targetId),
            ApprovalVoucherModule::Allocation => $this->budgetAllocationRepository->findForViewerOrFail($user, $targetId),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDepartmentId(
        User $user,
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        Transaction|Budget|BudgetAllocation|null $target,
        array $data,
    ): int {
        if ($action === ApprovalVoucherAction::Delete && $target !== null) {
            return (int) $target->department_id;
        }

        if (in_array($module, [ApprovalVoucherModule::Budget, ApprovalVoucherModule::Allocation], true)) {
            return $this->budgetAccessService->resolveBudgetDepartmentId();
        }

        return $this->departmentScopeService->resolveTransactionWritableDepartmentId(
            $user,
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $afterPayload
     */
    private function assertBudgetConflict(
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        ?array $afterPayload,
        ?int $ignoreBudgetId = null,
    ): void {
        if (
            $module !== ApprovalVoucherModule::Budget
            || $action === ApprovalVoucherAction::Delete
            || $afterPayload === null
        ) {
            return;
        }

        $hasConflict = $this->budgetRepository->existsActiveConflict(
            (int) $afterPayload['department_id'],
            (int) $afterPayload['category_id'],
            (int) $afterPayload['month'],
            (int) $afterPayload['year'],
            $ignoreBudgetId,
        );

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'category_id' => 'An active budget already exists for this category and month.',
            ]);
        }

        $validationMessage = $this->budgetAllocationSummaryService->getCategoryBudgetValidationMessage(
            (int) $afterPayload['department_id'],
            (int) $afterPayload['month'],
            (int) $afterPayload['year'],
            (float) $afterPayload['amount_limit'],
            $ignoreBudgetId,
        );

        if ($validationMessage !== null) {
            throw ValidationException::withMessages([
                'amount_limit' => $validationMessage,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $afterPayload
     */
    private function assertAllocationConflict(
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        ?array $afterPayload,
        ?int $ignoreAllocationId = null,
    ): void {
        if (
            $module !== ApprovalVoucherModule::Allocation
            || $action === ApprovalVoucherAction::Delete
            || $afterPayload === null
        ) {
            return;
        }

        $hasConflict = $this->budgetAllocationRepository->existsActiveConflict(
            (int) $afterPayload['department_id'],
            (int) $afterPayload['month'],
            (int) $afterPayload['year'],
            $ignoreAllocationId,
        );

        if (! $hasConflict) {
            $currentAllocated = $this->budgetRepository->sumActiveAmountLimitForPeriod(
                (int) $afterPayload['department_id'],
                (int) $afterPayload['month'],
                (int) $afterPayload['year'],
            );

            if (round((float) $afterPayload['amount_limit'], 2) < $currentAllocated) {
                throw ValidationException::withMessages([
                    'amount_limit' => 'The total allocation cannot be lower than the active category budgets for this month.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'month' => 'An active total allocation already exists for this month and year.',
        ]);
    }

    private function assertAllocationDeleteIsDisabled(
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
    ): void {
        if ($module !== ApprovalVoucherModule::Allocation || $action !== ApprovalVoucherAction::Delete) {
            return;
        }

        throw ValidationException::withMessages([
            'approval_voucher' => 'Monthly budget removal requests are no longer supported.',
        ]);
    }
}
