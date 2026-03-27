<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Http\Resources\BudgetAllocationResource;
use App\Http\Resources\BudgetResource;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\ReportsRepository;
use App\Services\Budget\BudgetAccessService;
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
        private readonly DepartmentScopeService $departmentScopeService,
        private readonly FinancialManagementDepartmentService $financialManagementDepartmentService,
    ) {}

    public function index(IndexReportRequest $request): Response
    {
        $validated = $request->validated();

        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $financialManagementDepartment = $this->financialManagementDepartmentService->getOrFail();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );
        $canViewBudgetSummaries = $this->budgetAccessService->canViewSummaries($request->user());

        $yearOptions = collect($this->reportsRepository->getDistinctYears($scope['department_id']));

        if ($canViewBudgetSummaries) {
            $yearOptions = $yearOptions->merge(
                $this->budgetRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year),
            );
            $yearOptions = $yearOptions->merge(
                $this->budgetAllocationRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year),
            );
        }

        $budgetSummary = $this->budgetRepository->getMonthlySummary(
            $financialManagementDepartment->id,
            CarbonImmutable::create($year, $month, 1),
        );
        $activeAllocation = $this->budgetAllocationRepository->getActiveForPeriod(
            $financialManagementDepartment->id,
            $month,
            $year,
        );

        if ($activeAllocation !== null) {
            $activeAllocation->setAttribute('total_allocated', $budgetSummary['total_allocated']);
        }

        return Inertia::render('Reports/Index', [
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => $department->toSummaryArray())
                ->values(),
            'filters' => [
                'month' => $month,
                'year' => $year,
                'department' => $scope['department_id'],
            ],
            'department_scope' => $scope,
            'summary' => [
                'monthly' => $this->reportsRepository->getMonthlyTotals($scope['department_id'], $month, $year),
                'yearly' => $this->reportsRepository->getYearlyTotals($scope['department_id'], $year),
            ],
            'breakdowns' => [
                'expenses_by_category' => $this->reportsRepository->getExpensesByCategory($scope['department_id'], $month, $year),
                'budget_vs_actual' => ! $canViewBudgetSummaries
                    ? []
                    : BudgetResource::collection(
                        $this->budgetRepository->getForIndex($financialManagementDepartment->id, $month, $year)
                    )->resolve(),
            ],
            'charts' => [
                'income_vs_expenses' => $this->reportsRepository->getIncomeVsExpensesByMonth($scope['department_id'], $year),
                'spending_trend' => $this->reportsRepository->getSpendingTrend($scope['department_id'], $month, $year),
            ],
            'options' => [
                'months' => $this->reportsRepository->getMonthOptions()->values(),
                'years' => $yearOptions
                    ->unique()
                    ->sortDesc()
                    ->values()
                    ->all(),
            ],
            'budget_summary' => ! $canViewBudgetSummaries ? null : [
                'scope_label' => 'Central monthly budget',
                'financial_management_department' => $financialManagementDepartment->toSummaryArray(),
                'active_allocation' => $activeAllocation === null
                    ? null
                    : (new BudgetAllocationResource($activeAllocation))->resolve(),
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
            ],
        ]);
    }
}
