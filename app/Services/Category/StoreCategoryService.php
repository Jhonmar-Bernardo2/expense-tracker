<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Repositories\CategoryRepository;

class StoreCategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function handle(int $userId, array $data): Category
    {
        return $this->categoryRepository->createForUser($userId, $data);
    }
}
