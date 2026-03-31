<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CategoryBudgetPreset */
class CategoryBudgetPresetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amountLimit = $this->pivot?->amount_limit;
        $categoryId = $this->pivot?->category_id;

        return [
            'id' => $this->id,
            'category_id' => $categoryId === null ? null : (int) $categoryId,
            'name' => $this->name,
            'amount_limit' => $amountLimit === null ? null : round((float) $amountLimit, 2),
            'items' => $this->whenLoaded(
                'items',
                fn () => CategoryBudgetPresetItemResource::collection($this->items)->resolve($request),
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
