<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Http\Resources\BudgetResource;
use App\Repositories\BudgetRepository;
use App\Repositories\ReportsRepository;
use App\Services\Department\DepartmentScopeService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsRepository $reportsRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {
    }

    public function index(IndexReportRequest $request): Response
    {
        $validated = $request->validated();

        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        return Inertia::render('Reports/Index', [
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
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
                'budget_vs_actual' => BudgetResource::collection(
                    $this->budgetRepository->getForIndex($scope['department_id'], $month, $year)
                ),
            ],
            'charts' => [
                'income_vs_expenses' => $this->reportsRepository->getIncomeVsExpensesByMonth($scope['department_id'], $year),
                'spending_trend' => $this->reportsRepository->getSpendingTrend($scope['department_id'], $month, $year),
            ],
            'options' => [
                'months' => $this->reportsRepository->getMonthOptions()->values(),
                'years' => $this->reportsRepository->getDistinctYears($scope['department_id']),
            ],
        ]);
    }
}
