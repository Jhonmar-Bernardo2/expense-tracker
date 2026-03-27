<?php

namespace App\Services\ApprovalVoucher;

use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use App\Models\ApprovalVoucher;
use App\Models\User;
use App\Repositories\ApprovalVoucherRepository;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;

class StoreApprovalVoucherService
{
    public function __construct(
        private readonly ApprovalVoucherRepository $approvalVoucherRepository,
        private readonly ApprovalVoucherPayloadService $approvalVoucherPayloadService,
        private readonly ApprovalVoucherAttachmentService $approvalVoucherAttachmentService,
        private readonly ApproveApprovalVoucherService $approveApprovalVoucherService,
        private readonly ActivityLogService $activityLogService,
        private readonly ApprovalVoucherNotificationService $approvalVoucherNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): ApprovalVoucher
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($user, $data, &$storedFiles): ApprovalVoucher {
                $payload = $this->approvalVoucherPayloadService->buildDraftPayload($user, $data);
                $shouldAutoSubmit = (bool) ($data['auto_submit'] ?? false);

                $approvalVoucher = $this->approvalVoucherRepository->create([
                    'voucher_no' => 'PENDING',
                    'department_id' => $payload['department_id'],
                    'requested_by' => $user->id,
                    'module' => $payload['module']->value,
                    'action' => $payload['action']->value,
                    'status' => $shouldAutoSubmit
                        ? ApprovalVoucherStatus::PendingApproval->value
                        : ApprovalVoucherStatus::Draft->value,
                    'target_id' => $payload['target_id'],
                    'before_payload' => $payload['before_payload'],
                    'after_payload' => $payload['after_payload'],
                    'remarks' => $data['remarks'] ?? null,
                    'submitted_at' => $shouldAutoSubmit ? now() : null,
                ]);

                $approvalVoucher = $this->approvalVoucherRepository->updateRecord($approvalVoucher, [
                    'voucher_no' => $this->approvalVoucherRepository->formatVoucherNumber($approvalVoucher),
                ]);

                $this->approvalVoucherAttachmentService->syncForVoucher(
                    $user,
                    $approvalVoucher,
                    $data,
                    $storedFiles,
                );

                $this->activityLogService->logApprovalVoucherCreated($user, $approvalVoucher);

                if ($shouldAutoSubmit) {
                    $this->activityLogService->logApprovalVoucherSubmitted($user, $approvalVoucher);

                    if ($this->shouldAutoApplyTransactionRequest($user, $approvalVoucher)) {
                        return $this->approveApprovalVoucherService->applyImmediately(
                            $user,
                            $approvalVoucher,
                            $data['remarks'] ?? $approvalVoucher->remarks,
                        );
                    }

                    $this->approvalVoucherNotificationService->notifyApproversOfSubmission($approvalVoucher);
                }

                return $approvalVoucher;
            });
        } catch (\Throwable $throwable) {
            $this->approvalVoucherAttachmentService->cleanupStoredFiles($storedFiles);

            throw $throwable;
        }
    }

    private function shouldAutoApplyTransactionRequest(User $user, ApprovalVoucher $approvalVoucher): bool
    {
        return $user->isFinancialManagement()
            && $approvalVoucher->module === ApprovalVoucherModule::Transaction
            && $approvalVoucher->status === ApprovalVoucherStatus::PendingApproval;
    }
}
