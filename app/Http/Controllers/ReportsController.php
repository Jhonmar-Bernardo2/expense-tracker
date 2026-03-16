<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Http\Resources\BudgetResource;
use App\Repositories\BudgetRepository;
use App\Repositories\ReportsRepository;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsRepository $reportsRepository,
        private readonly BudgetRepository $budgetRepository,
    ) {
    }

    public function index(IndexReportRequest $request): Response
    {
        $validated = $request->validated();

        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);

        return Inertia::render('Reports/Index', [
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'summary' => [
                'monthly' => $this->reportsRepository->getMonthlyTotals($request->user()->id, $month, $year),
                'yearly' => $this->reportsRepository->getYearlyTotals($request->user()->id, $year),
            ],
            'breakdowns' => [
                'expenses_by_category' => $this->reportsRepository->getExpensesByCategory($request->user()->id, $month, $year),
                'budget_vs_actual' => BudgetResource::collection(
                    $this->budgetRepository->getForIndex($request->user()->id, $month, $year)
                ),
            ],
            'charts' => [
                'income_vs_expenses' => $this->reportsRepository->getIncomeVsExpensesByMonth($request->user()->id, $year),
                'spending_trend' => $this->reportsRepository->getSpendingTrend($request->user()->id, $month, $year),
            ],
            'options' => [
                'months' => $this->reportsRepository->getMonthOptions()->values(),
                'years' => $this->reportsRepository->getDistinctYears($request->user()->id),
            ],
        ]);
    }
}
