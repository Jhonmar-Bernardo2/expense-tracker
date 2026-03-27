<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBudgetRequest;
use App\Http\Requests\UpsertBudgetRequest;
use App\Http\Resources\BudgetAllocationResource;
use App\Http\Resources\BudgetResource;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;
use App\Services\Budget\BudgetAccessService;
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
        private readonly CategoryRepository $categoryRepository,
        private readonly BudgetAccessService $budgetAccessService,
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
        $budgetSummary = $this->budgetRepository->getMonthlySummary($financialManagementDepartment->id, $summaryDate);
        $activeAllocation = $this->budgetAllocationRepository->getActiveForPeriod(
            $financialManagementDepartment->id,
            $month,
            $year,
        );

        if ($activeAllocation !== null) {
            $activeAllocation->setAttribute('total_allocated', $budgetSummary['total_allocated']);
        }

        return Inertia::render('Budgets/Index', [
            'budgets' => BudgetResource::collection(
                $this->budgetRepository->getForIndex($financialManagementDepartment->id, $month, $year)
            ),
            'active_allocation' => $activeAllocation === null
                ? null
                : new BudgetAllocationResource($activeAllocation),
            'allocation_summary' => [
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
            'categories' => $this->categoryRepository
                ->getExpenseOptions()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values(),
            'departments' => [
                $financialManagementDepartment->toSummaryArray(),
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
                'department' => $financialManagementDepartment->id,
            ],
            'department_scope' => [
                'department_id' => $financialManagementDepartment->id,
                'selected_department' => $financialManagementDepartment->toSummaryArray(),
                'can_select_department' => false,
                'is_all_departments' => false,
            ],
            'financial_management_department' => $financialManagementDepartment->toSummaryArray(),
            'months' => collect(range(1, 12))->map(fn (int $monthOption) => [
                'value' => $monthOption,
                'label' => CarbonImmutable::create($year, $monthOption, 1)->format('F'),
            ])->values(),
            'years' => collect($this->budgetRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year))
                ->merge($this->budgetAllocationRepository->getYearOptions($financialManagementDepartment->id, (int) $now->year))
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
        ]);
    }

    public function store(UpsertBudgetRequest $request): RedirectResponse
    {
        $departmentId = $this->budgetAccessService->resolveBudgetDepartmentId();

        $this->budgetRepository->create(
            $request->user(),
            $departmentId,
            $request->validated(),
        );

        return back()->with('success', 'Category budget added.');
    }

    public function update(UpsertBudgetRequest $request, int $budget): RedirectResponse
    {
        $existingBudget = $this->budgetRepository->findForViewerOrFail($request->user(), $budget);

        $this->budgetRepository->update($existingBudget, [
            ...$request->validated(),
            'department_id' => $this->budgetAccessService->resolveBudgetDepartmentId(),
        ]);

        return back()->with('success', 'Category budget updated.');
    }

    public function destroy(Request $request, int $budget): RedirectResponse
    {
        abort_unless(
            $this->budgetAccessService->canManageCategoryBudgets($request->user()),
            403,
        );

        $existingBudget = $this->budgetRepository->findForViewerOrFail($request->user(), $budget);
        $this->budgetRepository->archive($existingBudget);

        return back()->with('success', 'Category budget removed.');
    }
}
