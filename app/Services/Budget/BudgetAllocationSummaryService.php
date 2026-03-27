<?php

namespace App\Services\Budget;

use App\Models\BudgetAllocation;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use Carbon\CarbonImmutable;

class BudgetAllocationSummaryService
{
    public const MISSING_APPROVED_BUDGET_MESSAGE = 'An approved monthly total allocation is required before category budgets can be managed.';

    public const NO_AVAILABLE_BUDGET_MESSAGE = 'Wala nang available budget. Kailangan munang mag-request ulit ng panibagong budget kay Admin.';

    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetAllocationRepository $budgetAllocationRepository,
    ) {}

    /**
     * @return array{
     *     active_allocation: BudgetAllocation|null,
     *     summary: array{
     *         approved_allocation: float,
     *         total_approved_budget: float,
     *         total_budgeted: float,
     *         total_allocated: float,
     *         total_allocated_budget: float,
     *         total_unallocated: float,
     *         remaining_budget: float,
     *         total_spent: float,
     *         total_remaining: float,
     *         remaining_after_spending: float,
     *         categories_over_budget: int,
     *         can_allocate_category_budgets: bool,
     *         allocation_block_message: string|null
     *     }
     * }
     */
    public function getPeriodSummary(int $departmentId, CarbonImmutable $date): array
    {
        $budgetSummary = $this->budgetRepository->getMonthlySummary($departmentId, $date);
        $activeAllocation = $this->budgetAllocationRepository->getActiveForPeriod(
            $departmentId,
            $date->month,
            $date->year,
        );
        $approvedBudget = round((float) ($activeAllocation?->amount_limit ?? 0), 2);
        $allocatedBudget = $budgetSummary['total_allocated'];
        $remainingBudget = round($approvedBudget - $allocatedBudget, 2);
        $remainingAfterSpending = round($approvedBudget - $budgetSummary['total_spent'], 2);

        if ($activeAllocation !== null) {
            $activeAllocation->setAttribute('total_allocated', $allocatedBudget);
        }

        return [
            'active_allocation' => $activeAllocation,
            'summary' => [
                'approved_allocation' => $approvedBudget,
                'total_approved_budget' => $approvedBudget,
                'total_budgeted' => $budgetSummary['total_budgeted'],
                'total_allocated' => $allocatedBudget,
                'total_allocated_budget' => $allocatedBudget,
                'total_unallocated' => $remainingBudget,
                'remaining_budget' => $remainingBudget,
                'total_spent' => $budgetSummary['total_spent'],
                'total_remaining' => $remainingAfterSpending,
                'remaining_after_spending' => $remainingAfterSpending,
                'categories_over_budget' => $budgetSummary['categories_over_budget'],
                'can_allocate_category_budgets' => $activeAllocation !== null && $remainingBudget > 0,
                'allocation_block_message' => $this->resolveAllocationBlockMessage(
                    $activeAllocation,
                    $remainingBudget,
                ),
            ],
        ];
    }

    /**
     * @return array{
     *     active_allocation: BudgetAllocation|null,
     *     approved_budget: float,
     *     allocated_budget: float,
     *     remaining_budget: float,
     *     max_allocatable_amount: float
     * }
     */
    public function getCategoryBudgetCapacity(
        int $departmentId,
        int $month,
        int $year,
        ?int $ignoreBudgetId = null,
    ): array {
        $activeAllocation = $this->budgetAllocationRepository->getActiveForPeriod(
            $departmentId,
            $month,
            $year,
        );
        $approvedBudget = round((float) ($activeAllocation?->amount_limit ?? 0), 2);
        $allocatedBudget = $this->budgetRepository->sumActiveAmountLimitForPeriod(
            $departmentId,
            $month,
            $year,
            $ignoreBudgetId,
        );
        $remainingBudget = round($approvedBudget - $allocatedBudget, 2);

        return [
            'active_allocation' => $activeAllocation,
            'approved_budget' => $approvedBudget,
            'allocated_budget' => $allocatedBudget,
            'remaining_budget' => $remainingBudget,
            'max_allocatable_amount' => max($remainingBudget, 0),
        ];
    }

    public function getCategoryBudgetValidationMessage(
        int $departmentId,
        int $month,
        int $year,
        float $requestedAmount,
        ?int $ignoreBudgetId = null,
    ): ?string {
        $capacity = $this->getCategoryBudgetCapacity(
            $departmentId,
            $month,
            $year,
            $ignoreBudgetId,
        );

        if ($capacity['active_allocation'] === null) {
            return self::MISSING_APPROVED_BUDGET_MESSAGE;
        }

        if ($capacity['max_allocatable_amount'] <= 0) {
            return self::NO_AVAILABLE_BUDGET_MESSAGE;
        }

        if (round($requestedAmount, 2) > $capacity['max_allocatable_amount']) {
            return sprintf(
                'Hindi puwedeng lumagpas ang total ng budget per category sa approved budget ni Admin. Available na lang para ma-allocate: %s.',
                $this->formatCurrency($capacity['max_allocatable_amount']),
            );
        }

        return null;
    }

    private function resolveAllocationBlockMessage(
        ?BudgetAllocation $activeAllocation,
        float $remainingBudget,
    ): ?string {
        if ($activeAllocation === null) {
            return self::MISSING_APPROVED_BUDGET_MESSAGE;
        }

        if ($remainingBudget <= 0) {
            return self::NO_AVAILABLE_BUDGET_MESSAGE;
        }

        return null;
    }

    private function formatCurrency(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }
}
