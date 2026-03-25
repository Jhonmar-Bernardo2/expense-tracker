<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApprovalMemo */
class ApprovalMemoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'memo_no' => $this->memo_no,
            'department_id' => $this->department_id,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'module' => $this->module->value,
            'module_label' => $this->module->label(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'subject' => $this->resolveSubject(),
            'remarks' => $this->remarks,
            'admin_remarks' => $this->admin_remarks,
            'rejection_reason' => $this->rejection_reason,
            'permissions' => [
                'can_edit' => $actor !== null ? $this->canEditRequest($actor) : false,
                'can_submit' => $actor !== null ? $this->canSubmitRequest($actor) : false,
                'can_approve' => $actor !== null ? $this->canApprove($actor) : false,
                'can_reject' => $actor !== null ? $this->canReject($actor) : false,
                'can_delete' => $actor !== null ? $this->canDeleteRequest($actor) : false,
                'can_print' => $actor !== null ? $this->canPrint($actor) : false,
            ],
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
            'requested_by_user' => $this->whenLoaded('requestedBy', fn () => [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ]),
            'approved_by_user' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy === null
                ? null
                : [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                    'email' => $this->approvedBy->email,
                ]),
            'linked_approval_voucher' => $this->whenLoaded('linkedApprovalVoucher', fn () => $this->linkedApprovalVoucher === null
                ? null
                : [
                    'id' => $this->linkedApprovalVoucher->id,
                    'voucher_no' => $this->linkedApprovalVoucher->voucher_no,
                    'status' => $this->linkedApprovalVoucher->status->value,
                    'status_label' => $this->linkedApprovalVoucher->status->label(),
                ]),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'rejected_at' => $this->rejected_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
