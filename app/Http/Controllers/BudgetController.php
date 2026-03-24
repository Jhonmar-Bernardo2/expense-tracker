<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBudgetRequest;
use App\Http\Requests\UpsertBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;
use App\Services\Budget\StoreBudgetService;
use App\Services\Budget\UpdateBudgetService;
use App\Services\Department\DepartmentScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DepartmentScopeService $departmentScopeService,
    ) {
    }

    public function index(IndexBudgetRequest $request): Response
    {
        $validated = $request->validated();

        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $scope = $this->departmentScopeService->resolveFilterScope(
            $request->user(),
            isset($validated['department']) ? (int) $validated['department'] : null,
        );

        return Inertia::render('Budgets/Index', [
            'budgets' => BudgetResource::collection(
                $this->budgetRepository->getForIndex($scope['department_id'], $month, $year)
            ),
            'categories' => $this->categoryRepository
                ->getExpenseOptions()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values(),
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
            'months' => collect(range(1, 12))->map(fn (int $monthOption) => [
                'value' => $monthOption,
                'label' => CarbonImmutable::create($year, $monthOption, 1)->format('F'),
            ])->values(),
            'years' => $this->budgetRepository->getYearOptions($scope['department_id'], (int) $now->year),
        ]);
    }

    public function store(
        UpsertBudgetRequest $request,
        StoreBudgetService $storeBudgetService,
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['department_id'] = $this->departmentScopeService->resolveWritableDepartmentId(
            $request->user(),
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );

        $storeBudgetService->handle($request->user(), $validated['department_id'], $validated);

        return back()->with('success', 'Budget created.');
    }

    public function update(
        UpsertBudgetRequest $request,
        int $budget,
        UpdateBudgetService $updateBudgetService,
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['department_id'] = $this->departmentScopeService->resolveWritableDepartmentId(
            $request->user(),
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );
        $existingBudget = $this->budgetRepository->findForViewerOrFail($request->user(), $budget);

        $updateBudgetService->handle($existingBudget, $validated);

        return back()->with('success', 'Budget updated.');
    }

    public function destroy(Request $request, int $budget): RedirectResponse
    {
        $existingBudget = $this->budgetRepository->findForViewerOrFail($request->user(), $budget);

        $this->budgetRepository->delete($existingBudget);

        return back()->with('success', 'Budget deleted.');
    }
}
