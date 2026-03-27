<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'department_id' => $this->department_id,
            'origin_approval_voucher_id' => $this->origin_approval_voucher_id,
            'voided_by_approval_voucher_id' => $this->voided_by_approval_voucher_id,
            'category_id' => $this->category_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'amount' => (string) $this->amount,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'voided_at' => $this->voided_at?->toDateTimeString(),
            'is_voided' => $this->isVoided(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'type' => $this->category->type->value,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                ...$this->department->toSummaryArray(),
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
