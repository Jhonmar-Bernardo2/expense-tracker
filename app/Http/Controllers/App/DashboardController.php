<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\IndexDashboardRequest;
use App\Http\Resources\App\DashboardPageResource;
use App\Repositories\ApprovalVoucherRepository;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\Budget\BudgetAllocationSummaryService;
use App\Services\Department\DepartmentScopeService;
use App\Services\Department\FinancialManagementDepartmentService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository,
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetAllocationRepository $budgetAllocationRepository,
        private readonly BudgetAccessService $budgetAccessService,
        private readonly BudgetAllocationSummaryService $budgetAllocationSummaryService,
        private readonly DepartmentScopeService $departmentScopeService,
        private readonly FinancialManagementDepartmentService $financialManagementDepartmentService,
    ) {}

    public function index(IndexDashboardRequest $request): Response
    {
        $now = CarbonImmutable::now();
        $validated = $request->validated();
        $user = $request->user();
        $financialManagementDepartment = $this->financialManagementDepartmentService->getOrFail();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $user,
            isset($validated['department']) ? (int) $validated['department'] : null,
        );
        $canViewBudgetSummaries = $this->budgetAccessService->canViewSummaries($user);
        $periodSummary = $this->budgetAllocationSummaryService->getPeriodSummary(
            $financialManagementDepartment->id,
            $now,
        );
        $mode = $this->resolveDashboardViewMode($user);

        return Inertia::render('app/Dashboard', (new DashboardPageResource([
            'departments' => $this->departmentScopeService->getOptionsFor($user),
            'department_scope' => $scope,
            'totals' => $this->dashboardRepository->getTotals($scope['department_id']),
            'current_month' => $this->dashboardRepository->getMonthSummary($scope['department_id'], $now),
            'can_view_budget_summaries' => $canViewBudgetSummaries,
            'financial_management_department' => $financialManagementDepartment,
            'active_allocation' => $periodSummary['active_allocation'],
            'budget_period_summary' => $periodSummary['summary'],
            'current_month_statuses' => $canViewBudgetSummaries
                ? $this->budgetRepository->getForIndex($financialManagementDepartment->id, $now->month, $now->year)
                : collect(),
            'recent_transactions' => $this->dashboardRepository->getRecentTransactions($scope['department_id']),
            'charts' => [
                'expenses_by_category' => $this->dashboardRepository->getCurrentMonthExpensesByCategory($scope['department_id'], $now),
                'income_vs_expenses' => $this->dashboardRepository->getIncomeVsExpensesByMonth($scope['department_id'], (int) $now->year),
            ],
            'mode' => $mode,
            'requester_counts' => $this->approvalVoucherRepository->getRequesterDashboardCounts($user, $now),
            'admin_pending_allocation_count' => $mode === 'admin'
                ? $this->approvalVoucherRepository->countPendingForModule($user, \App\Enums\ApprovalVoucherModule::Allocation)
                : 0,
            'admin_allocation_items' => $mode === 'admin'
                ? $this->approvalVoucherRepository->getRecentByModuleForDashboard(
                    $user,
                    \App\Enums\ApprovalVoucherModule::Allocation,
                    5,
                    null,
                    true,
                )
                : collect(),
            'finance_pending_transaction_count' => $mode === 'financial_management'
                ? $this->approvalVoucherRepository->countPendingForModule($user, \App\Enums\ApprovalVoucherModule::Transaction)
                : 0,
            'finance_pending_items' => $mode === 'financial_management'
                ? $this->approvalVoucherRepository->getRecentByModuleForDashboard(
                    $user,
                    \App\Enums\ApprovalVoucherModule::Transaction,
                    5,
                    \App\Enums\ApprovalVoucherStatus::PendingApproval,
                    true,
                )
                : collect(),
            'finance_recent_items' => $mode === 'financial_management'
                ? $this->approvalVoucherRepository->getRecentByModuleForDashboard(
                    $user,
                    \App\Enums\ApprovalVoucherModule::Transaction,
                    5,
                    null,
                    true,
                )
                : collect(),
            'staff_recent_requests' => $mode === 'staff'
                ? $this->approvalVoucherRepository->getRecentRequestsByRequester($user, 5)
                : collect(),
        ]))->resolve($request));
    }

    private function resolveDashboardViewMode($user): string
    {
        if ($user->isAdmin()) {
            return 'admin';
        }

        if ($user->isFinancialManagement()) {
            return 'financial_management';
        }

        return 'staff';
    }
}
