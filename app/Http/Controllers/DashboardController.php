<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexDashboardRequest;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\TransactionResource;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use App\Services\Department\DepartmentScopeService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {
    }

    public function index(IndexDashboardRequest $request): Response
    {
        $now = CarbonImmutable::now();
        $validated = $request->validated();
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        return Inertia::render('Dashboard', [
            'departments' => $this->departmentScopeService
                ->getOptionsFor($request->user())
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->values(),
            'department_scope' => $scope,
            'totals' => $this->dashboardRepository->getTotals($scope['department_id']),
            'current_month' => $this->dashboardRepository->getMonthSummary($scope['department_id'], $now),
            'budgets' => [
                'current_month_summary' => $this->budgetRepository->getMonthlySummary($scope['department_id'], $now),
                'current_month_statuses' => BudgetResource::collection(
                    $this->budgetRepository->getForIndex($scope['department_id'], $now->month, $now->year)
                ),
            ],
            'recent_transactions' => TransactionResource::collection(
                $this->dashboardRepository->getRecentTransactions($scope['department_id'])
            ),
            'charts' => [
                'expenses_by_category' => $this->dashboardRepository->getCurrentMonthExpensesByCategory($scope['department_id'], $now),
                'income_vs_expenses' => $this->dashboardRepository->getIncomeVsExpensesByMonth($scope['department_id'], (int) $now->year),
            ],
        ]);
    }
}
