<?php

namespace App\Services\Budget;

use App\Models\User;
use App\Services\Department\FinancialManagementDepartmentService;

class BudgetAccessService
{
    public function __construct(
        private readonly FinancialManagementDepartmentService $financialManagementDepartmentService,
    ) {
    }

    public function canViewPage(?User $user): bool
    {
        return $user?->canViewCentralBudgetPage() ?? false;
    }

    public function canManageRequests(?User $user): bool
    {
        return $this->canManageCategoryBudgets($user);
    }

    public function canManageCategoryBudgets(?User $user): bool
    {
        return $user?->canManageCategoryBudgets() ?? false;
    }

    public function canRequestAllocations(?User $user): bool
    {
        return $user?->canRequestBudgetAllocations() ?? false;
    }

    public function canApproveTransactions(?User $user): bool
    {
        return $user?->canApproveTransactionRequests() ?? false;
    }

    public function canApproveAllocations(?User $user): bool
    {
        return $user?->canApproveBudgetAllocations() ?? false;
    }

    public function canViewSummaries(?User $user): bool
    {
        return $user?->canViewCentralBudgetSummaries() ?? false;
    }

    public function resolveBudgetDepartmentId(): int
    {
        return $this->financialManagementDepartmentService->getOrFail()->id;
    }

    /**
     * @return array{
     *     can_view_page: bool,
     *     can_manage_requests: bool,
     *     can_manage_category_budgets: bool,
     *     can_request_allocations: bool,
     *     can_approve_transactions: bool,
     *     can_approve_allocations: bool,
     *     can_view_summaries: bool,
     *     is_centralized: bool,
     *     financial_management_department: array{id: int, name: string, is_financial_management: bool, is_locked: bool}
     * }
     */
    public function forUser(?User $user): array
    {
        $department = $this->financialManagementDepartmentService->getOrCreate();

        return [
            'can_view_page' => $this->canViewPage($user),
            'can_manage_requests' => $this->canManageRequests($user),
            'can_manage_category_budgets' => $this->canManageCategoryBudgets($user),
            'can_request_allocations' => $this->canRequestAllocations($user),
            'can_approve_transactions' => $this->canApproveTransactions($user),
            'can_approve_allocations' => $this->canApproveAllocations($user),
            'can_view_summaries' => $this->canViewSummaries($user),
            'is_centralized' => true,
            'financial_management_department' => $department->toSummaryArray(),
        ];
    }
}
