<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transactionsCount = $this->resolveCount('transactions');
        $budgetsCount = $this->resolveCount('budgets');
        $budgetPresets = $this->resource->relationLoaded('budgetPresets')
            ? $this->budgetPresets
            : collect();
        $budgetPresetCount = $budgetPresets->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'transaction_count' => $transactionsCount,
            'budget_count' => $budgetsCount,
            'budget_presets' => CategoryBudgetPresetResource::collection($budgetPresets)->resolve($request),
            'has_budget_preset' => $budgetPresetCount > 0,
            'budget_preset_count' => $budgetPresetCount,
            'can_delete' => $transactionsCount === 0
                && $budgetsCount === 0
                && $budgetPresetCount === 0,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolveCount(string $relation): int
    {
        $countAttribute = "{$relation}_count";

        if (isset($this->{$countAttribute})) {
            return (int) $this->{$countAttribute};
        }

        if ($this->resource->relationLoaded($relation)) {
            return $this->resource->{$relation}->count();
        }

        return 0;
    }
}
