<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalMemo;
use App\Models\User;
use App\Repositories\ApprovalMemoRepository;
use App\Services\ActivityLogService;
use App\Services\Department\DepartmentScopeService;
use Illuminate\Support\Facades\DB;

class StoreApprovalMemoService
{
    public function __construct(
        private readonly ApprovalMemoRepository $approvalMemoRepository,
        private readonly ApprovalMemoNotificationService $approvalMemoNotificationService,
        private readonly DepartmentScopeService $departmentScopeService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): ApprovalMemo
    {
        return DB::transaction(function () use ($user, $data): ApprovalMemo {
            $shouldAutoSubmit = (bool) ($data['auto_submit'] ?? false);
            $approvalMemo = ApprovalMemo::query()->create([
                'memo_no' => 'PENDING',
                'department_id' => $this->departmentScopeService->resolveWritableDepartmentId(
                    $user,
                    isset($data['department_id']) ? (int) $data['department_id'] : null,
                ),
                'requested_by' => $user->id,
                'module' => ApprovalVoucherModule::from((string) $data['module'])->value,
                'action' => ApprovalMemoAction::from((string) $data['action'])->value,
                'status' => $shouldAutoSubmit
                    ? ApprovalMemoStatus::PendingApproval->value
                    : ApprovalMemoStatus::Draft->value,
                'remarks' => $data['remarks'] ?? null,
                'submitted_at' => $shouldAutoSubmit ? now() : null,
            ]);

            $approvalMemo->update([
                'memo_no' => $this->approvalMemoRepository->formatMemoNumber($approvalMemo),
            ]);

            $approvalMemo = $approvalMemo->refresh();

            $this->activityLogService->logApprovalMemoCreated($user, $approvalMemo);

            if ($shouldAutoSubmit) {
                $this->activityLogService->logApprovalMemoSubmitted($user, $approvalMemo);
                $this->approvalMemoNotificationService->notifyAdminsOfSubmission($approvalMemo);
            }

            return $approvalMemo;
        });
    }
}
