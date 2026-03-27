<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Repositories\CategoryRepository;

class DeleteCategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function handle(Category $category): ?string
    {
        if ($this->categoryRepository->hasRelatedRecords($category)) {
            return 'This category cannot be deleted because it is already used by transactions or budgets.';
        }

        $this->categoryRepository->delete($category);

        return null;
    }
}
