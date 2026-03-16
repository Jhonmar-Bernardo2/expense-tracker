<?php

namespace App\Http\Controllers;

use App\Http\Resources\BudgetResource;
use App\Http\Resources\TransactionResource;
use App\Repositories\BudgetRepository;
use App\Repositories\DashboardRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository,
        private readonly BudgetRepository $budgetRepository,
    ) {
    }

    public function index(Request $request): Response
    {
        $now = CarbonImmutable::now();

        return Inertia::render('Dashboard', [
            'totals' => $this->dashboardRepository->getTotals($request->user()->id),
            'current_month' => $this->dashboardRepository->getMonthSummary($request->user()->id, $now),
            'budgets' => [
                'current_month_summary' => $this->budgetRepository->getMonthlySummary($request->user()->id, $now),
                'current_month_statuses' => BudgetResource::collection(
                    $this->budgetRepository->getForIndex($request->user()->id, $now->month, $now->year)
                ),
            ],
            'recent_transactions' => TransactionResource::collection(
                $this->dashboardRepository->getRecentTransactions($request->user()->id)
            ),
            'charts' => [
                'expenses_by_category' => $this->dashboardRepository->getCurrentMonthExpensesByCategory($request->user()->id, $now),
                'income_vs_expenses' => $this->dashboardRepository->getIncomeVsExpensesByMonth($request->user()->id, (int) $now->year),
            ],
        ]);
    }
}
