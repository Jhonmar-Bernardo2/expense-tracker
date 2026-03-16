<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBudgetRequest;
use App\Http\Requests\UpsertBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;
use App\Services\Budget\StoreBudgetService;
use App\Services\Budget\UpdateBudgetService;
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
    ) {
    }

    public function index(IndexBudgetRequest $request): Response
    {
        $validated = $request->validated();

        $now = CarbonImmutable::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);

        return Inertia::render('Budgets/Index', [
            'budgets' => BudgetResource::collection(
                $this->budgetRepository->getForIndex($request->user()->id, $month, $year)
            ),
            'categories' => $this->categoryRepository
                ->getExpenseOptions($request->user()->id)
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values(),
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'months' => collect(range(1, 12))->map(fn (int $monthOption) => [
                'value' => $monthOption,
                'label' => CarbonImmutable::create($year, $monthOption, 1)->format('F'),
            ])->values(),
            'years' => $this->budgetRepository->getYearOptions($request->user()->id, (int) $now->year),
        ]);
    }

    public function store(
        UpsertBudgetRequest $request,
        StoreBudgetService $storeBudgetService,
    ): RedirectResponse {
        $storeBudgetService->handle($request->user()->id, $request->validated());

        return back()->with('success', 'Budget created.');
    }

    public function update(
        UpsertBudgetRequest $request,
        int $budget,
        UpdateBudgetService $updateBudgetService,
    ): RedirectResponse {
        $existingBudget = $this->budgetRepository->findForUserOrFail($request->user()->id, $budget);

        $updateBudgetService->handle($existingBudget, $request->validated());

        return back()->with('success', 'Budget updated.');
    }

    public function destroy(Request $request, int $budget): RedirectResponse
    {
        $existingBudget = $this->budgetRepository->findForUserOrFail($request->user()->id, $budget);

        $this->budgetRepository->delete($existingBudget);

        return back()->with('success', 'Budget deleted.');
    }
}
