<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpsertCategoryBudgetPresetRequest;
use App\Http\Resources\Finance\CategoryBudgetPresetIndexPageResource;
use App\Repositories\CategoryBudgetPresetRepository;
use App\Repositories\CategoryRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\CategoryBudgetPreset\DeleteCategoryBudgetPresetService;
use App\Services\CategoryBudgetPreset\StoreCategoryBudgetPresetService;
use App\Services\CategoryBudgetPreset\UpdateCategoryBudgetPresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryBudgetPresetController extends Controller
{
    public function __construct(
        private readonly CategoryBudgetPresetRepository $categoryBudgetPresetRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly BudgetAccessService $budgetAccessService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $this->budgetAccessService->canViewPage($request->user()),
            403,
        );

        return Inertia::render('finance/BudgetPresets/Index', (new CategoryBudgetPresetIndexPageResource([
            'budget_presets' => $this->categoryBudgetPresetRepository->getForManagement(),
            'categories' => $this->categoryRepository->getExpenseOptions(),
        ]))->resolve($request));
    }

    public function store(
        UpsertCategoryBudgetPresetRequest $request,
        StoreCategoryBudgetPresetService $storeCategoryBudgetPresetService,
    ): RedirectResponse {
        $storeCategoryBudgetPresetService->handle($request->validated());

        return back()->with('success', 'Category budget preset created.');
    }

    public function update(
        UpsertCategoryBudgetPresetRequest $request,
        int $categoryBudgetPreset,
        UpdateCategoryBudgetPresetService $updateCategoryBudgetPresetService,
    ): RedirectResponse {
        $existingPreset = $this->categoryBudgetPresetRepository->findOrFail($categoryBudgetPreset);

        $updateCategoryBudgetPresetService->handle($existingPreset, $request->validated());

        return back()->with('success', 'Category budget preset updated.');
    }

    public function destroy(
        Request $request,
        int $categoryBudgetPreset,
        DeleteCategoryBudgetPresetService $deleteCategoryBudgetPresetService,
    ): RedirectResponse {
        abort_unless(
            $request->user() !== null
            && $request->user()->canManageCategoryBudgets(),
            403,
        );

        $existingPreset = $this->categoryBudgetPresetRepository->findOrFail($categoryBudgetPreset);

        $deleteCategoryBudgetPresetService->handle($existingPreset);

        return back()->with('success', 'Category budget preset removed.');
    }
}
