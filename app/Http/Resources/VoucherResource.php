<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherItem;
use App\Models\VoucherLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Voucher */
class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $liquidationTotal = $this->resolveLiquidationTotal();
        $voucherId = $this->id;

        return [
            'id' => $this->id,
            'voucher_no' => $this->voucher_no,
            'department_id' => $this->department_id,
            'requested_by_user_id' => $this->requested_by,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'purpose' => $this->purpose,
            'remarks' => $this->remarks,
            'rejection_reason' => $this->rejection_reason,
            'liquidation_return_reason' => $this->liquidation_return_reason,
            'requested_amount' => $this->decimalOrNull($this->requested_amount),
            'approved_amount' => $this->decimalOrNull($this->approved_amount),
            'released_amount' => $this->decimalOrNull($this->released_amount),
            'liquidation_total' => $liquidationTotal,
            'liquidation_due_date' => $this->liquidation_due_date?->toDateString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'liquidation_submitted_at' => $this->liquidation_submitted_at?->toISOString(),
            'liquidation_reviewed_at' => $this->liquidation_reviewed_at?->toISOString(),
            'posted_at' => $this->posted_at?->toISOString(),
            'attachments_count' => $this->resolveAttachmentsCount(),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->actorPayload($this->requestedBy)),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->actorPayload($this->approvedBy)),
            'released_by' => $this->whenLoaded('releasedBy', fn () => $this->actorPayload($this->releasedBy)),
            'liquidation_reviewed_by' => $this->whenLoaded(
                'liquidationReviewedBy',
                fn () => $this->actorPayload($this->liquidationReviewedBy),
            ),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(
                fn (VoucherItem $item) => [
                    'id' => $item->id,
                    'category_id' => $item->category_id,
                    'description' => $item->description,
                    'amount' => $this->decimalOrNull($item->amount),
                    'expense_date' => $item->expense_date?->toDateString(),
                    'category' => $item->relationLoaded('category') && $item->category !== null
                        ? [
                            'id' => $item->category->id,
                            'name' => $item->category->name,
                            'type' => $item->category->type->value,
                        ]
                        : null,
                ]
            )->values()),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(
                fn (VoucherAttachment $attachment) => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'download_url' => route('vouchers.attachments.download', [
                        'voucher' => $voucherId,
                        'attachment' => $attachment->id,
                    ]),
                    'uploaded_by' => $attachment->relationLoaded('uploadedBy') && $attachment->uploadedBy !== null
                        ? $this->actorPayload($attachment->uploadedBy)
                        : null,
                    'created_at' => $attachment->created_at?->toISOString(),
                ]
            )->values()),
            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(
                fn (VoucherLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'action_label' => str($log->action)->replace('_', ' ')->headline()->toString(),
                    'from_status' => $log->from_status?->value,
                    'from_status_label' => $log->from_status?->label(),
                    'to_status' => $log->to_status?->value,
                    'to_status_label' => $log->to_status?->label(),
                    'remarks' => $log->remarks,
                    'user' => $log->relationLoaded('user') && $log->user !== null
                        ? $this->actorPayload($log->user)
                        : null,
                    'created_at' => $log->created_at?->toISOString(),
                ]
            )->values()),
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(
                fn ($transaction) => [
                    'id' => $transaction->id,
                    'title' => $transaction->title,
                    'amount' => (string) $transaction->amount,
                    'transaction_date' => $transaction->transaction_date?->toDateString(),
                    'category' => $transaction->relationLoaded('category') && $transaction->category !== null
                        ? [
                            'id' => $transaction->category->id,
                            'name' => $transaction->category->name,
                            'type' => $transaction->category->type->value,
                        ]
                        : null,
                ]
            )->values()),
            'permissions' => $this->permissions($request->user()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function actorPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(?User $user): array
    {
        if ($user === null) {
            return [
                'can_edit_request' => false,
                'can_submit_request' => false,
                'can_approve' => false,
                'can_reject' => false,
                'can_release' => false,
                'can_submit_liquidation' => false,
                'can_return_liquidation' => false,
                'can_approve_liquidation' => false,
            ];
        }

        return [
            'can_edit_request' => $this->resource->canEditRequest($user),
            'can_submit_request' => $this->resource->canSubmitRequest($user),
            'can_approve' => $this->resource->canApprove($user),
            'can_reject' => $this->resource->canReject($user),
            'can_release' => $this->resource->canRelease($user),
            'can_submit_liquidation' => $this->resource->canSubmitLiquidation($user),
            'can_return_liquidation' => $this->resource->canReturnLiquidation($user),
            'can_approve_liquidation' => $this->resource->canApproveLiquidation($user),
        ];
    }

    private function resolveLiquidationTotal(): ?string
    {
        if ($this->resource->relationLoaded('items')) {
            $amount = $this->resource->items->sum(fn (VoucherItem $item) => (float) $item->amount);

            return number_format($amount, 2, '.', '');
        }

        if (isset($this->items_sum_amount)) {
            return number_format((float) $this->items_sum_amount, 2, '.', '');
        }

        return null;
    }

    private function resolveAttachmentsCount(): int
    {
        if (isset($this->attachments_count)) {
            return (int) $this->attachments_count;
        }

        if ($this->resource->relationLoaded('attachments')) {
            return $this->resource->attachments->count();
        }

        return 0;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
