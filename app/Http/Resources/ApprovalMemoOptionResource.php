<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApprovalMemo */
class ApprovalMemoOptionResource extends JsonResource
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
            'department_name' => $this->department?->name,
            'module' => $this->module->value,
            'module_label' => $this->module->label(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'remarks' => $this->remarks,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'download_url' => route('approval-memos.download', $this->resource),
            'print_url' => route('approval-memos.print', [
                'approvalMemo' => $this->resource,
                'autoprint' => 1,
            ]),
        ];
    }
}
