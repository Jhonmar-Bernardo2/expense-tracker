<?php

namespace App\Http\Resources;

use App\Enums\ApprovalVoucherModule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApprovalVoucher */
class ApprovalVoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'voucher_no' => $this->voucher_no,
            'department_id' => $this->department_id,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'module' => $this->module->value,
            'module_label' => $this->module->label(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'target_id' => $this->target_id,
            'subject' => $this->resolveSubject(),
            'before_payload' => $this->before_payload,
            'after_payload' => $this->after_payload,
            'remarks' => $this->remarks,
            'rejection_reason' => $this->rejection_reason,
            'permissions' => [
                'can_edit' => $actor !== null ? $this->canEditRequest($actor) : false,
                'can_submit' => $actor !== null ? $this->canSubmitRequest($actor) : false,
                'can_approve' => $actor !== null ? $this->canApprove($actor) : false,
                'can_reject' => $actor !== null ? $this->canReject($actor) : false,
            ],
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
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'rejected_at' => $this->rejected_at?->toDateTimeString(),
            'applied_at' => $this->applied_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function resolveSubject(): string
    {
        $payload = $this->after_payload ?? $this->before_payload ?? [];

        if ($this->module === ApprovalVoucherModule::Transaction) {
            return (string) ($payload['title'] ?? "Transaction #{$this->target_id}");
        }

        $month = isset($payload['month']) ? (int) $payload['month'] : null;
        $year = isset($payload['year']) ? (int) $payload['year'] : null;

        if ($month !== null && $year !== null && $month >= 1 && $month <= 12) {
            return sprintf('Budget for %s %d', date('F', mktime(0, 0, 0, $month, 1)), $year);
        }

        return $this->target_id === null
            ? 'Budget request'
            : "Budget #{$this->target_id}";
    }
}
