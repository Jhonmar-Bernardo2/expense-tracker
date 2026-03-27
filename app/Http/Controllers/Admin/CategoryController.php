<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexCategoryRequest;
use App\Http\Requests\Admin\UpsertCategoryRequest;
use App\Http\Resources\Admin\CategoryIndexPageResource;
use App\Repositories\CategoryRepository;
use App\Services\Category\DeleteCategoryService;
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
    ) {}

    public function index(IndexCategoryRequest $request): Response
    {
        $validated = $request->validated();
        $type = isset($validated['type'])
            ? TransactionType::from($validated['type'])
            : null;

        return Inertia::render('admin/Categories/Index', (new CategoryIndexPageResource([
            'categories' => $this->categoryRepository->getForIndex($type),
            'type' => $type,
        ]))->resolve($request));
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

    public function destroy(
        Request $request,
        int $category,
        DeleteCategoryService $deleteCategoryService,
    ): RedirectResponse {
        $existingCategory = $this->categoryRepository->findOrFail($category);
        $error = $deleteCategoryService->handle($existingCategory);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Category deleted.');
    }
}
