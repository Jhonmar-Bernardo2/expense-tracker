<?php

namespace App\Services\CategoryBudgetPreset;

use App\Models\CategoryBudgetPreset;
use App\Repositories\CategoryBudgetPresetRepository;

class DeleteCategoryBudgetPresetService
{
    public function __construct(
        private readonly CategoryBudgetPresetRepository $categoryBudgetPresetRepository,
    ) {}

    public function handle(CategoryBudgetPreset $preset): void
    {
        $this->categoryBudgetPresetRepository->delete($preset);
    }
}
