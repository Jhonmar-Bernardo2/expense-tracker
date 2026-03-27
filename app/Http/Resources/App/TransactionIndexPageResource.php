<?php

namespace App\Http\Resources\App;

use App\Enums\TransactionType;
use App\Http\Resources\Concerns\ResolvesPaginatedResources;
use App\Http\Resources\Shared\CategoryResource;
use App\Http\Resources\Shared\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionIndexPageResource extends JsonResource
{
    use ResolvesPaginatedResources;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transactions' => $this->paginatedResource(
                $request,
                $this['transactions'],
                TransactionResource::class,
            ),
            'categories' => CategoryResource::collection($this['categories'])->resolve($request),
            'departments' => $this['departments']
                ->map(fn ($department) => $department->toSummaryArray())
                ->values()
                ->all(),
            'filters' => [
                'type' => $this['type']?->value,
                'category' => $this['category_id'],
                'month' => $this['month'],
                'year' => $this['year'],
                'search' => $this['search'],
                'department' => $this['department_scope']['department_id'],
            ],
            'department_scope' => $this['department_scope'],
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $transactionType) => [
                'value' => $transactionType->value,
                'label' => str($transactionType->value)->headline()->toString(),
            ])->values()->all(),
            'years' => collect($this['years'])->values()->all(),
        ];
    }
}
