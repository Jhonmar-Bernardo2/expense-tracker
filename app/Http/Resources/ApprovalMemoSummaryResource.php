<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApprovalMemo */
class ApprovalMemoSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'memo_no' => $this->memo_no,
            'department_id' => $this->department_id,
            'module' => $this->module->value,
            'module_label' => $this->module->label(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'remarks' => $this->remarks,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'download_url' => $this->status === \App\Enums\ApprovalMemoStatus::Approved
                ? route('approval-memos.download', $this->resource)
                : null,
            'print_url' => $this->status === \App\Enums\ApprovalMemoStatus::Approved
                ? route('approval-memos.print', [
                    'approvalMemo' => $this->resource,
                    'autoprint' => 1,
                ])
                : null,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
        ];
    }
}
