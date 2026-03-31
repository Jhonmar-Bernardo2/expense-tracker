<?php

namespace App\Http\Resources\Finance;

use App\Http\Resources\Shared\CategoryBudgetPresetResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryBudgetPresetIndexPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'budget_presets' => CategoryBudgetPresetResource::collection($this['budget_presets'])->resolve($request),
            'categories' => $this['categories']
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
