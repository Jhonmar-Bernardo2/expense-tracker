<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\IndexReportRequest;
use App\Http\Resources\App\ReportsPageResource;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\ReportsRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\Budget\BudgetAllocationSummaryService;
use App\Services\Department\DepartmentScopeService;
use App\Services\Department\FinancialManagementDepartmentService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsRepository $reportsRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetAllocationRepository $budgetAllocationRepository,
        private readonly BudgetAccessService $budgetAccessService,
        private readonly BudgetAllocationSummaryService $budgetAllocationSummaryService,
        private readonly DepartmentScopeService $departmentScopeService,
        private readonly FinancialManagementDepartmentService $financialManagementDepartmentService,
    ) {}

    public function index(IndexReportRequest $request): Response
    {
        $validated = $request->validated();
        $user = $request->user();
        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $financialManagementDepartment = $this->financialManagementDepartmentService->getOrFail();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $user,
            isset($validated['department']) ? (int) $validated['department'] : null,
        );
        $canViewBudgetSummaries = $this->budgetAccessService->canViewSummaries($user);

        $yearOptions = collect($this->reportsRepository->getDistinctYears($scope['department_id']));

        if ($canViewBudgetSummaries) {
            $yearOptions = $yearOptions
                ->merge($this->budgetRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year))
                ->merge($this->budgetAllocationRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year));
        }

        $periodSummary = $this->budgetAllocationSummaryService->getPeriodSummary(
            $financialManagementDepartment->id,
            CarbonImmutable::create($year, $month, 1),
        );

        return Inertia::render('app/Reports/Index', (new ReportsPageResource([
            'departments' => $this->departmentScopeService->getOptionsFor($user),
            'filters' => [
                'month' => $month,
                'year' => $year,
                'department' => $scope['department_id'],
            ],
            'department_scope' => $scope,
            'monthly_summary' => $this->reportsRepository->getMonthlyTotals($scope['department_id'], $month, $year),
            'yearly_summary' => $this->reportsRepository->getYearlyTotals($scope['department_id'], $year),
            'expenses_by_category' => $this->reportsRepository->getExpensesByCategory($scope['department_id'], $month, $year),
            'budget_vs_actual' => $this->budgetRepository->getForIndex($financialManagementDepartment->id, $month, $year),
            'income_vs_expenses' => $this->reportsRepository->getIncomeVsExpensesByMonth($scope['department_id'], $year),
            'spending_trend' => $this->reportsRepository->getSpendingTrend($scope['department_id'], $month, $year),
            'months' => $this->reportsRepository->getMonthOptions(),
            'years' => $yearOptions->all(),
            'can_view_budget_summaries' => $canViewBudgetSummaries,
            'financial_management_department' => $financialManagementDepartment,
            'active_allocation' => $periodSummary['active_allocation'],
            'budget_period_summary' => $periodSummary['summary'],
        ]))->resolve($request));
    }
}
