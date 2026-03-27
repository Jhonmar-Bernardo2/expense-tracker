<?php

namespace App\Http\Resources\App;

use App\Enums\TransactionType;
use App\Http\Resources\Shared\ActivityLogResource;
use App\Http\Resources\Shared\ApprovalVoucherResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalVoucherShowPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'approval_voucher' => (new ApprovalVoucherResource($this['approval_voucher']))->resolve($request),
            'activity_logs' => ActivityLogResource::collection($this['activity_logs'])->resolve($request),
            'categories' => $this['categories']
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                ])
                ->values()
                ->all(),
            'departments' => $this['departments']
                ->map(fn ($department) => $department->toSummaryArray())
                ->values()
                ->all(),
            'transaction_types' => collect(TransactionType::cases())->map(fn (TransactionType $type) => [
                'value' => $type->value,
                'label' => str($type->value)->headline()->toString(),
            ])->values()->all(),
        ];
    }
}
