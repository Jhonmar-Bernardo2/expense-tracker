<?php

namespace App\Services\CategoryBudgetPreset;

use App\Models\CategoryBudgetPreset;
use App\Repositories\CategoryBudgetPresetRepository;

class UpdateCategoryBudgetPresetService
{
    public function __construct(
        private readonly CategoryBudgetPresetRepository $categoryBudgetPresetRepository,
    ) {}

    /**
     * @param  array{name: string, items: list<array{category_id: int, amount_limit: mixed}>}  $data
     */
    public function handle(CategoryBudgetPreset $preset, array $data): CategoryBudgetPreset
    {
        return $this->categoryBudgetPresetRepository->update($preset, $data);
    }
}
