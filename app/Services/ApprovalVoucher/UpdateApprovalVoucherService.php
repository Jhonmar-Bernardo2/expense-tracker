<?php

namespace App\Services\ApprovalVoucher;

use App\Models\ApprovalVoucher;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, ApprovalVoucher $approvalVoucher, array $data): ApprovalVoucher
    {
        if (! $approvalVoucher->canEditRequest($user)) {
            throw ValidationException::withMessages([
                'approval_voucher' => 'Only your draft or rejected requests can be edited.',
            ]);
        }

        $payload = $this->approvalVoucherPayloadService->buildDraftPayload($user, $data);

        $approvalVoucher->update([
            'department_id' => $payload['department_id'],
            'module' => $payload['module']->value,
            'action' => $payload['action']->value,
            'target_id' => $payload['target_id'],
            'before_payload' => $payload['before_payload'],
            'after_payload' => $payload['after_payload'],
            'remarks' => $data['remarks'] ?? null,
        ]);

        return $approvalVoucher->refresh();
    }
}
