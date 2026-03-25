<?php

namespace App\Services\ApprovalMemo;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalMemo;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Department\DepartmentScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateApprovalMemoService
{
    public function __construct(
        private readonly DepartmentScopeService $departmentScopeService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, ApprovalMemo $approvalMemo, array $data): ApprovalMemo
    {
        if (! $approvalMemo->canEditRequest($user)) {
            throw ValidationException::withMessages([
                'approval_memo' => 'Only your draft or rejected memo requests can be edited.',
            ]);
        }

        return DB::transaction(function () use ($user, $approvalMemo, $data): ApprovalMemo {
            $approvalMemo->update([
                'department_id' => $this->departmentScopeService->resolveWritableDepartmentId(
                    $user,
                    isset($data['department_id']) ? (int) $data['department_id'] : null,
                ),
                'module' => ApprovalVoucherModule::from((string) $data['module'])->value,
                'action' => ApprovalMemoAction::from((string) $data['action'])->value,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $approvalMemo = $approvalMemo->refresh();

            $this->activityLogService->logApprovalMemoUpdated($user, $approvalMemo);

            return $approvalMemo;
        });
    }
}
