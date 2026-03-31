<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\User;
use App\Repositories\CategoryBudgetPresetRepository;
use App\Repositories\BudgetRepository;
use Illuminate\Database\Eloquent\Collection;

class StoreBudgetService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly CategoryBudgetPresetRepository $categoryBudgetPresetRepository,
    ) {
    }

    /**
     * @param  array{source?: string, preset_id?: int, category_id?: int|null, month: int, year: int, amount_limit?: mixed|null}  $data
     */
    public function handle(User $user, int $departmentId, array $data): Budget|Collection
    {
        if (($data['source'] ?? 'manual') === 'preset') {
            $preset = $this->categoryBudgetPresetRepository->findOrFail((int) $data['preset_id']);

            return $this->budgetRepository->createManyFromPreset(
                $user,
                $departmentId,
                $preset,
                (int) $data['month'],
                (int) $data['year'],
            );
        }

        return $this->budgetRepository->create($user, $departmentId, $data);
    }
}
