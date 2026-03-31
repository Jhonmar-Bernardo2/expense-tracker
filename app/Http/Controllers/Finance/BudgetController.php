<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\IndexBudgetRequest;
use App\Http\Requests\Finance\UpsertBudgetRequest;
use App\Http\Resources\Finance\BudgetIndexPageResource;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryBudgetPresetRepository;
use App\Repositories\CategoryRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\Budget\BudgetAllocationSummaryService;
use App\Services\Budget\DeleteBudgetService;
use App\Services\Budget\StoreBudgetService;
use App\Services\Budget\UpdateBudgetService;
use App\Services\Department\FinancialManagementDepartmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetAllocationRepository $budgetAllocationRepository,
        private readonly CategoryBudgetPresetRepository $categoryBudgetPresetRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly BudgetAccessService $budgetAccessService,
        private readonly BudgetAllocationSummaryService $budgetAllocationSummaryService,
        private readonly FinancialManagementDepartmentService $financialManagementDepartmentService,
    ) {}

    public function index(IndexBudgetRequest $request): Response
    {
        $validated = $request->validated();
        $financialManagementDepartment = $this->financialManagementDepartmentService->getOrFail();

        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $summaryDate = CarbonImmutable::create($year, $month, 1);
        $periodSummary = $this->budgetAllocationSummaryService->getPeriodSummary(
            $financialManagementDepartment->id,
            $summaryDate,
        );

        return Inertia::render('finance/Budgets/Index', (new BudgetIndexPageResource([
            'budgets' => $this->budgetRepository->getForIndex($financialManagementDepartment->id, $month, $year),
            'active_allocation' => $periodSummary['active_allocation'],
            'allocation_summary' => $periodSummary['summary'],
            'budget_presets' => $this->categoryBudgetPresetRepository->getForManagement(),
            'categories' => $this->categoryRepository->getExpenseOptions(),
            'financial_management_department' => $financialManagementDepartment,
            'month' => $month,
            'year' => $year,
            'years' => collect($this->budgetRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year))
                ->merge($this->budgetAllocationRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year))
                ->all(),
        ]))->resolve($request));
    }

    public function store(
        UpsertBudgetRequest $request,
        StoreBudgetService $storeBudgetService,
    ): RedirectResponse {
        $departmentId = $this->budgetAccessService->resolveBudgetDepartmentId();

        $storeBudgetService->handle(
            $request->user(),
            $departmentId,
            $request->validated(),
        );

        return back()->with(
            'success',
            $request->input('source', 'manual') === 'preset'
                ? 'Category budgets added from preset.'
                : 'Category budget added.',
        );
    }

    public function update(
        UpsertBudgetRequest $request,
        int $budget,
        UpdateBudgetService $updateBudgetService,
    ): RedirectResponse {
        $existingBudget = $this->budgetRepository->findForViewerOrFail($request->user(), $budget);

        $updateBudgetService->handle($existingBudget, [
            ...$request->validated(),
            'department_id' => $this->budgetAccessService->resolveBudgetDepartmentId(),
        ]);

        return back()->with('success', 'Category budget updated.');
    }

    public function destroy(
        Request $request,
        int $budget,
        DeleteBudgetService $deleteBudgetService,
    ): RedirectResponse {
        abort_unless(
            $this->budgetAccessService->canManageCategoryBudgets($request->user()),
            403,
        );

        $existingBudget = $this->budgetRepository->findForViewerOrFail($request->user(), $budget);
        $deleteBudgetService->handle($existingBudget);

        return back()->with('success', 'Category budget removed.');
    }
}
