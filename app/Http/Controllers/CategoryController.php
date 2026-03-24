<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\IndexCategoryRequest;
use App\Http\Requests\UpsertCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Repositories\CategoryRepository;
use App\Services\Category\StoreCategoryService;
use App\Services\Category\UpdateCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function index(IndexCategoryRequest $request): Response
    {
        $validated = $request->validated();

        $type = isset($validated['type'])
            ? TransactionType::from($validated['type'])
            : null;

        return Inertia::render('Categories/Index', [
            'categories' => CategoryResource::collection(
                $this->categoryRepository->getForIndex($type)
            ),
            'filters' => [
                'type' => $type?->value,
            ],
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $transactionType) => [
                'value' => $transactionType->value,
                'label' => str($transactionType->value)->headline()->toString(),
            ])->values(),
        ]);
    }

    public function store(
        UpsertCategoryRequest $request,
        StoreCategoryService $storeCategoryService,
    ): RedirectResponse {
        $storeCategoryService->handle($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function update(
        UpsertCategoryRequest $request,
        int $category,
        UpdateCategoryService $updateCategoryService,
    ): RedirectResponse {
        $existingCategory = $this->categoryRepository->findOrFail($category);

        $updateCategoryService->handle($existingCategory, $request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Request $request, int $category): RedirectResponse
    {
        $existingCategory = $this->categoryRepository->findOrFail($category);

        if ($this->categoryRepository->hasRelatedRecords($existingCategory)) {
            return back()->with(
                'error',
                'This category cannot be deleted because it is already used by transactions or budgets.'
            );
        }

        $this->categoryRepository->delete($existingCategory);

        return back()->with('success', 'Category deleted.');
    }
}
