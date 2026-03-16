<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Budget */
class BudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amountLimit = round((float) $this->amount_limit, 2);
        $amountSpent = round((float) ($this->amount_spent ?? 0), 2);
        $amountRemaining = round($amountLimit - $amountSpent, 2);
        $percentageUsed = $amountLimit > 0
            ? round(($amountSpent / $amountLimit) * 100, 2)
            : 0.0;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category_name' => (string) ($this->category_name ?? $this->category?->name ?? ''),
            'month' => (int) $this->month,
            'year' => (int) $this->year,
            'amount_limit' => $amountLimit,
            'amount_spent' => $amountSpent,
            'amount_remaining' => $amountRemaining,
            'percentage_used' => $percentageUsed,
            'is_over_budget' => $amountSpent > $amountLimit,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
