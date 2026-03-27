<?php

namespace App\Http\Resources\Admin;

use App\Enums\TransactionType;
use App\Http\Resources\Shared\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryIndexPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'categories' => CategoryResource::collection($this['categories'])->resolve($request),
            'filters' => [
                'type' => $this['type']?->value,
            ],
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $transactionType) => [
                'value' => $transactionType->value,
                'label' => str($transactionType->value)->headline()->toString(),
            ])->values()->all(),
        ];
    }
}
