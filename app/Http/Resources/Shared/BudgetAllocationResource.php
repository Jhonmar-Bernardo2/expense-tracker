<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BudgetAllocation */
class BudgetAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $approvedAmount = round((float) $this->amount_limit, 2);
        $totalAllocated = round((float) ($this->total_allocated ?? 0), 2);
        $amountRemaining = round($approvedAmount - $totalAllocated, 2);

        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'origin_approval_voucher_id' => $this->origin_approval_voucher_id,
            'archived_by_approval_voucher_id' => $this->archived_by_approval_voucher_id,
            'month' => (int) $this->month,
            'year' => (int) $this->year,
            'amount_limit' => $approvedAmount,
            'approved_amount' => $approvedAmount,
            'total_allocated' => $totalAllocated,
            'amount_remaining' => $amountRemaining,
            'is_over_allocated' => $totalAllocated > $approvedAmount,
            'archived_at' => $this->archived_at?->toDateTimeString(),
            'is_archived' => $this->isArchived(),
            'department' => $this->whenLoaded('department', fn () => $this->department->toSummaryArray()),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
