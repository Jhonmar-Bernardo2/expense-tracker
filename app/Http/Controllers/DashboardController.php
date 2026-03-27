<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Http\Requests\IndexDashboardRequest;
use App\Http\Resources\ApprovalVoucherResource;
use App\Http\Resources\BudgetAllocationResource;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\TransactionResource;
use App\Repositories\ApprovalVoucherRepository;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\Department\DepartmentScopeService;
use App\Services\Department\FinancialManagementDepartmentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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
        private readonly DepartmentScopeService $departmentScopeService,
        private readonly FinancialManagementDepartmentService $financialManagementDepartmentService,
    ) {
    }

    public function index(IndexDashboardRequest $request): Response
    {
        $now = CarbonImmutable::now();
        $validated = $request->validated();
        $financialManagementDepartment = $this->financialManagementDepartmentService->getOrFail();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );
        $canViewBudgetSummaries = $this->budgetAccessService->canViewSummaries($request->user());
        $budgetSummary = $this->budgetRepository->getMonthlySummary($financialManagementDepartment->id, $now);
        $activeAllocation = $this->budgetAllocationRepository->getActiveForPeriod(
            $financialManagementDepartment->id,
            $now->month,
            $now->year,
        );

        if ($activeAllocation !== null) {
            $activeAllocation->setAttribute('total_allocated', $budgetSummary['total_allocated']);
        }

        $totals = $this->dashboardRepository->getTotals($scope['department_id']);
        $currentMonth = $this->dashboardRepository->getMonthSummary($scope['department_id'], $now);
        $recentTransactions = $this->dashboardRepository->getRecentTransactions($scope['department_id']);
        $charts = [
            'expenses_by_category' => $this->dashboardRepository->getCurrentMonthExpensesByCategory($scope['department_id'], $now),
            'income_vs_expenses' => $this->dashboardRepository->getIncomeVsExpensesByMonth($scope['department_id'], (int) $now->year),
        ];
        $budgetPayload = ! $canViewBudgetSummaries ? null : [
            'scope_label' => 'Central allocation',
            'financial_management_department' => $financialManagementDepartment->toSummaryArray(),
            'active_allocation' => $activeAllocation === null
                ? null
                : new BudgetAllocationResource($activeAllocation),
            'current_month_summary' => [
                'approved_allocation' => round((float) ($activeAllocation?->amount_limit ?? 0), 2),
                'total_budgeted' => $budgetSummary['total_budgeted'],
                'total_allocated' => $budgetSummary['total_allocated'],
                'total_unallocated' => round(
                    (float) ($activeAllocation?->amount_limit ?? 0) - $budgetSummary['total_allocated'],
                    2,
                ),
                'total_spent' => $budgetSummary['total_spent'],
                'total_remaining' => round(
                    (float) ($activeAllocation?->amount_limit ?? 0) - $budgetSummary['total_spent'],
                    2,
                ),
                'categories_over_budget' => $budgetSummary['categories_over_budget'],
            ],
            'current_month_statuses' => BudgetResource::collection(
                $this->budgetRepository->getForIndex($financialManagementDepartment->id, $now->month, $now->year)
            ),
        ];

        return Inertia::render('Dashboard', [
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => $department->toSummaryArray())
                ->values(),
            'department_scope' => $scope,
            'totals' => $totals,
            'current_month' => $currentMonth,
            'budgets' => $budgetPayload,
            'recent_transactions' => TransactionResource::collection($recentTransactions),
            'charts' => $charts,
            'dashboard_view' => $this->buildDashboardView(
                $request,
                $scope,
                $totals,
                $currentMonth,
                $budgetPayload,
                $recentTransactions,
            ),
        ]);
    }

    /**
     * @param  array{
     *     department_id: int|null,
     *     selected_department: array{id: int, name: string, is_financial_management: bool, is_locked: bool}|null,
     *     can_select_department: bool,
     *     is_all_departments: bool
     * }  $scope
     * @param  array{income: float, expenses: float, balance: float}  $totals
     * @param  array{month: int, year: int, income: float, expenses: float, balance: float}  $currentMonth
     * @param  array{
     *     scope_label: string,
     *     financial_management_department: array{id: int, name: string, is_financial_management: bool, is_locked: bool},
     *     active_allocation: BudgetAllocationResource|null,
     *     current_month_summary: array{
     *         approved_allocation: float,
     *         total_budgeted: float,
     *         total_allocated: float,
     *         total_unallocated: float,
     *         total_spent: float,
     *         total_remaining: float,
     *         categories_over_budget: int
     *     },
     *     current_month_statuses: \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * }|null  $budgetPayload
     * @param  Collection<int, \App\Models\Transaction>  $recentTransactions
     * @return array<string, mixed>
     */
    private function buildDashboardView(
        IndexDashboardRequest $request,
        array $scope,
        array $totals,
        array $currentMonth,
        ?array $budgetPayload,
        Collection $recentTransactions,
    ): array {
        $user = $request->user();
        $mode = $this->resolveDashboardViewMode($user);
        $departmentName = $scope['selected_department']['name']
            ?? ($scope['is_all_departments'] ? 'all departments' : 'your department');
        $requesterCounts = $this->approvalVoucherRepository->getRequesterDashboardCounts($user, CarbonImmutable::now());

        return match ($mode) {
            'admin' => [
                'mode' => $mode,
                'title' => 'Executive Financial Dashboard',
                'description' => 'Monitor organization-wide cash flow, allocation approvals, and central finance health.',
                'primary_metrics' => [
                    $this->metric('total-income', 'Total income', $totals['income'], 'currency', 'Across the selected scope.', 'success'),
                    $this->metric('total-expenses', 'Total expenses', $totals['expenses'], 'currency', 'Across the selected scope.', 'warning'),
                    $this->metric('net-balance', 'Net balance', $totals['balance'], 'currency', 'Income minus expenses.', $totals['balance'] < 0 ? 'danger' : 'info'),
                    $this->metric(
                        'pending-allocation-approvals',
                        'Pending allocation approvals',
                        $this->approvalVoucherRepository->countPendingForModule($user, ApprovalVoucherModule::Allocation),
                        'number',
                        'Monthly full-budget requests awaiting admin review.',
                        'info',
                    ),
                ],
                'quick_actions' => [
                    $this->action(
                        'review-allocation-approvals',
                        'Review allocation approvals',
                        route('approval-vouchers.index', [
                            'module' => ApprovalVoucherModule::Allocation->value,
                            'status' => ApprovalVoucherStatus::PendingApproval->value,
                        ], false),
                        'default',
                        'file-text',
                    ),
                    $this->action('open-reports', 'Open reports', route('reports.index', [], false), 'outline', 'bar-chart-3'),
                    $this->action('review-central-budget', 'Review central budget', route('budgets.index', [], false), 'secondary', 'piggy-bank'),
                ],
                'primary_section' => $this->section(
                    'allocation-approvals',
                    'Latest critical approvals',
                    'Monthly allocation requests, sorted with pending items first.',
                    'No allocation approval requests are visible right now.',
                    ApprovalVoucherResource::collection(
                        $this->approvalVoucherRepository->getRecentByModuleForDashboard(
                            $user,
                            ApprovalVoucherModule::Allocation,
                            5,
                            null,
                            true,
                        )
                    )->resolve($request),
                ),
                'secondary_section' => null,
            ],
            'financial_management' => [
                'mode' => $mode,
                'title' => 'Financial Management Operations',
                'description' => 'Work the approval queue, manage category budgets, and track central allocation execution.',
                'primary_metrics' => [
                    $this->metric(
                        'approved-allocation',
                        'Approved allocation',
                        $budgetPayload['current_month_summary']['approved_allocation'] ?? 0,
                        'currency',
                        'Current approved monthly total.',
                        'info',
                    ),
                    $this->metric(
                        'allocated-categories',
                        'Allocated to categories',
                        $budgetPayload['current_month_summary']['total_allocated'] ?? 0,
                        'currency',
                        'Budget already assigned to categories.',
                        'info',
                    ),
                    $this->metric(
                        'unallocated',
                        'Unallocated',
                        $budgetPayload['current_month_summary']['total_unallocated'] ?? 0,
                        'currency',
                        'Still available for category assignment.',
                        'warning',
                    ),
                    $this->metric(
                        'spent',
                        'Spent organization-wide',
                        $budgetPayload['current_month_summary']['total_spent'] ?? 0,
                        'currency',
                        'Approved expense transactions this month.',
                        'warning',
                    ),
                    $this->metric(
                        'remaining',
                        'Remaining after spending',
                        $budgetPayload['current_month_summary']['total_remaining'] ?? 0,
                        'currency',
                        'Approved allocation minus actual spend.',
                        ($budgetPayload['current_month_summary']['total_remaining'] ?? 0) < 0 ? 'danger' : 'success',
                    ),
                    $this->metric(
                        'pending-transaction-approvals',
                        'Pending transaction approvals',
                        $this->approvalVoucherRepository->countPendingForModule($user, ApprovalVoucherModule::Transaction),
                        'number',
                        'Department requests waiting for Financial Management review.',
                        'info',
                    ),
                ],
                'quick_actions' => [
                    $this->action(
                        'review-transaction-requests',
                        'Review transaction requests',
                        route('approval-vouchers.index', [
                            'module' => ApprovalVoucherModule::Transaction->value,
                            'status' => ApprovalVoucherStatus::PendingApproval->value,
                        ], false),
                        'default',
                        'file-text',
                    ),
                    $this->action('manage-category-budgets', 'Manage category budgets', route('budgets.index', [], false), 'secondary', 'piggy-bank'),
                    $this->action('open-reports', 'Open reports', route('reports.index', [], false), 'outline', 'bar-chart-3'),
                ],
                'primary_section' => $this->section(
                    'transaction-approval-queue',
                    'Transaction approval queue',
                    'Pending department requests that need Financial Management action.',
                    'No pending transaction requests are waiting for review.',
                    ApprovalVoucherResource::collection(
                        $this->approvalVoucherRepository->getRecentByModuleForDashboard(
                            $user,
                            ApprovalVoucherModule::Transaction,
                            5,
                            ApprovalVoucherStatus::PendingApproval,
                            true,
                        )
                    )->resolve($request),
                ),
                'secondary_section' => $this->section(
                    'recent-department-requests',
                    'Recent department requests',
                    'Latest transaction requests across departments, including recently processed items.',
                    'No recent department requests are visible right now.',
                    ApprovalVoucherResource::collection(
                        $this->approvalVoucherRepository->getRecentByModuleForDashboard(
                            $user,
                            ApprovalVoucherModule::Transaction,
                            5,
                            null,
                            true,
                        )
                    )->resolve($request),
                ),
            ],
            default => [
                'mode' => $mode,
                'title' => 'Department Dashboard',
                'description' => "Track {$departmentName} activity, monitor your request statuses, and submit new transaction requests.",
                'primary_metrics' => [
                    $this->metric(
                        'my-pending-requests',
                        'My pending requests',
                        $requesterCounts['pending'],
                        'number',
                        'Requests still waiting for a final decision.',
                        'info',
                    ),
                    $this->metric(
                        'my-approved-requests',
                        'Approved this month',
                        $requesterCounts['approved_this_month'],
                        'number',
                        'Requests approved this month.',
                        'success',
                    ),
                    $this->metric(
                        'my-rejected-requests',
                        'Rejected this month',
                        $requesterCounts['rejected_this_month'],
                        'number',
                        'Requests rejected this month.',
                        'warning',
                    ),
                    $this->metric(
                        'department-expenses',
                        'Department expenses this month',
                        $currentMonth['expenses'],
                        'currency',
                        'Approved expense transactions for your department.',
                        'warning',
                    ),
                ],
                'quick_actions' => [
                    $this->action('request-transaction', 'Request transaction', route('transactions.index', [], false), 'default', 'receipt'),
                    $this->action('view-my-requests', 'View my requests', route('approval-vouchers.index', [], false), 'outline', 'file-text'),
                ],
                'primary_section' => $this->section(
                    'my-request-statuses',
                    'My request statuses',
                    'Your most recent approval vouchers, with the latest status first.',
                    'You have not submitted any approval requests yet.',
                    ApprovalVoucherResource::collection(
                        $this->approvalVoucherRepository->getRecentRequestsByRequester($user, 5)
                    )->resolve($request),
                ),
                'secondary_section' => null,
            ],
        };
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

    /**
     * @return array{id: string, label: string, value: float|int|string, format: string, helper: string|null, tone: string}
     */
    private function metric(
        string $id,
        string $label,
        float|int|string $value,
        string $format,
        ?string $helper = null,
        string $tone = 'default',
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'helper' => $helper,
            'tone' => $tone,
        ];
    }

    /**
     * @return array{id: string, label: string, href: string, variant: string, icon: string|null}
     */
    private function action(
        string $id,
        string $label,
        string $href,
        string $variant = 'outline',
        ?string $icon = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'href' => $href,
            'variant' => $variant,
            'icon' => $icon,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{id: string, title: string, description: string, empty_message: string, items: array<int, mixed>}
     */
    private function section(
        string $id,
        string $title,
        string $description,
        string $emptyMessage,
        array $items,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'empty_message' => $emptyMessage,
            'items' => $items,
        ];
    }
}
