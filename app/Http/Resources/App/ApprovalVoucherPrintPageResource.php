<?php

namespace App\Http\Resources\App;

use App\Http\Resources\Shared\ApprovalVoucherResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalVoucherPrintPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'approval_voucher' => (new ApprovalVoucherResource($this['approval_voucher']))->resolve($request),
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
        ];
    }
}
