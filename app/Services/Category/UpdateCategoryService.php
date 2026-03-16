<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Repositories\CategoryRepository;

class UpdateCategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function handle(Category $category, array $data): Category
    {
        return $this->categoryRepository->update($category, $data);
    }
}
