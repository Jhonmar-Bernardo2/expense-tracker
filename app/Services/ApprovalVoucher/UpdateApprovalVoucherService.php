<?php

namespace App\Services\ApprovalVoucher;

use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
        private readonly ApprovalVoucherAttachmentService $approvalVoucherAttachmentService,
        private readonly ActivityLogService $activityLogService,
    ) {}

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

        $storedFiles = [];

        try {
            return DB::transaction(function () use ($user, $approvalVoucher, $data, &$storedFiles): ApprovalVoucher {
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

                $approvalVoucher = $approvalVoucher->refresh();

                $this->approvalVoucherAttachmentService->syncForVoucher(
                    $user,
                    $approvalVoucher,
                    $data,
                    $storedFiles,
                );

                $this->activityLogService->logApprovalVoucherUpdated($user, $approvalVoucher);

                return $approvalVoucher;
            });
        } catch (\Throwable $throwable) {
            $this->approvalVoucherAttachmentService->cleanupStoredFiles($storedFiles);

            throw $throwable;
        }
    }
}
