<?php

namespace App\Repositories;

use App\Models\CategoryBudgetPreset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryBudgetPresetRepository
{
    /**
     * @return Collection<int, CategoryBudgetPreset>
     */
    public function getForManagement(): Collection
    {
        return CategoryBudgetPreset::query()
            ->with(['items.category' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    public function findOrFail(int $presetId): CategoryBudgetPreset
    {
        return CategoryBudgetPreset::query()
            ->with(['items.category' => fn ($query) => $query->orderBy('name')])
            ->findOrFail($presetId);
    }

    /**
     * @param  array{name: string, items: list<array{category_id: int, amount_limit: mixed}>}  $data
     */
    public function create(array $data): CategoryBudgetPreset
    {
        return DB::transaction(function () use ($data): CategoryBudgetPreset {
            $preset = CategoryBudgetPreset::query()->create([
                'name' => $data['name'],
            ]);

            $preset->categories()->sync($this->formatItemsForSync($data['items']));

            return $preset->load(['items.category']);
        });
    }

    /**
     * @param  array{name: string, items: list<array{category_id: int, amount_limit: mixed}>}  $data
     */
    public function update(CategoryBudgetPreset $preset, array $data): CategoryBudgetPreset
    {
        return DB::transaction(function () use ($preset, $data): CategoryBudgetPreset {
            $preset->update([
                'name' => $data['name'],
            ]);

            $preset->categories()->sync($this->formatItemsForSync($data['items']));

            return $preset->load(['items.category']);
        });
    }

    public function delete(CategoryBudgetPreset $preset): void
    {
        $preset->delete();
    }

    /**
     * @param  list<array{category_id: int, amount_limit: mixed}>  $items
     * @return array<int, array{amount_limit: mixed}>
     */
    private function formatItemsForSync(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            $syncData[$item['category_id']] = [
                'amount_limit' => $item['amount_limit'],
            ];
        }

        return $syncData;
    }
}
