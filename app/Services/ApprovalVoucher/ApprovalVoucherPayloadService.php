<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalVoucher;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use App\Services\Department\DepartmentScopeService;
use Illuminate\Validation\ValidationException;

class ApprovalVoucherPayloadService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly BudgetRepository $budgetRepository,
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
        $target = $this->findRequesterTarget($user, $module, $action, isset($data['target_id']) ? (int) $data['target_id'] : null);
        $departmentId = $this->resolveDepartmentId($user, $action, $target, $data);
        $beforePayload = $this->snapshotTarget($module, $target);
        $afterPayload = $this->buildAfterPayload($module, $action, $departmentId, $data);

        $this->assertBudgetConflict($module, $action, $afterPayload, $target?->id);

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
     * @return Transaction|Budget|null
     */
    public function resolveTargetForApproval(ApprovalVoucher $approvalVoucher): Transaction|Budget|null
    {
        return match ($approvalVoucher->action) {
            ApprovalVoucherAction::Create => null,
            default => match ($approvalVoucher->module) {
                ApprovalVoucherModule::Transaction => Transaction::query()
                    ->active()
                    ->find($approvalVoucher->target_id),
                ApprovalVoucherModule::Budget => Budget::query()
                    ->active()
                    ->find($approvalVoucher->target_id),
            },
        };
    }

    public function assertCanApply(ApprovalVoucher $approvalVoucher, Transaction|Budget|null $target): void
    {
        if ($approvalVoucher->action !== ApprovalVoucherAction::Create && $target === null) {
            $entity = $approvalVoucher->module === ApprovalVoucherModule::Transaction
                ? 'transaction'
                : 'budget';

            throw ValidationException::withMessages([
                'approval_voucher' => "The selected {$entity} is no longer active.",
            ]);
        }

        $this->assertBudgetConflict(
            $approvalVoucher->module,
            $approvalVoucher->action,
            $approvalVoucher->after_payload,
            $approvalVoucher->action === ApprovalVoucherAction::Update ? $approvalVoucher->target_id : null,
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

        if ($module === ApprovalVoucherModule::Transaction) {
            return [
                'department_id' => $departmentId,
                'category_id' => (int) $data['category_id'],
                'type' => (string) $data['type'],
                'title' => trim((string) $data['title']),
                'amount' => round((float) $data['amount'], 2),
                'description' => isset($data['description']) && $data['description'] !== ''
                    ? trim((string) $data['description'])
                    : null,
                'transaction_date' => (string) $data['transaction_date'],
            ];
        }

        return [
            'department_id' => $departmentId,
            'category_id' => (int) $data['category_id'],
            'month' => (int) $data['month'],
            'year' => (int) $data['year'],
            'amount_limit' => round((float) $data['amount_limit'], 2),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotTarget(ApprovalVoucherModule $module, Transaction|Budget|null $target): ?array
    {
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

        return null;
    }

    /**
     * @return Transaction|Budget|null
     */
    private function findRequesterTarget(
        User $user,
        ApprovalVoucherModule $module,
        ApprovalVoucherAction $action,
        ?int $targetId,
    ): Transaction|Budget|null {
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
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDepartmentId(
        User $user,
        ApprovalVoucherAction $action,
        Transaction|Budget|null $target,
        array $data,
    ): int {
        if ($action === ApprovalVoucherAction::Delete && $target !== null) {
            return (int) $target->department_id;
        }

        return $this->departmentScopeService->resolveWritableDepartmentId(
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

        if (! $hasConflict) {
            return;
        }

        throw ValidationException::withMessages([
            'category_id' => 'An active budget already exists for this category and month.',
        ]);
    }
}
