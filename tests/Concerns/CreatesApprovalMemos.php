<?php

namespace Tests\Concerns;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
use App\Models\ApprovalMemo;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;

trait CreatesApprovalMemos
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createApprovedMemo(
        User $requester,
        ?Department $department = null,
        array $overrides = [],
    ): ApprovalMemo {
        $department ??= $requester->department ?? Department::factory()->create();
        $approver = User::factory()->admin()->create([
            'department_id' => $department->id,
        ]);

        $approvalMemo = ApprovalMemo::query()->create(array_merge([
            'memo_no' => sprintf('AM-2026-%05d', ApprovalMemo::query()->count() + 1),
            'department_id' => $department->id,
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'module' => ApprovalVoucherModule::Transaction->value,
            'action' => ApprovalMemoAction::Create->value,
            'status' => ApprovalMemoStatus::Approved->value,
            'remarks' => 'Approved memo ready.',
            'admin_remarks' => 'Reviewed and approved.',
            'rejection_reason' => null,
            'submitted_at' => '2026-03-24 09:00:00',
            'approved_at' => '2026-03-24 10:00:00',
            'rejected_at' => null,
            'created_at' => '2026-03-24 08:30:00',
            'updated_at' => '2026-03-24 10:00:00',
        ], $overrides));

        return $approvalMemo->fresh([
            'linkedApprovalVoucher',
        ]);
    }

    protected function makeApprovalMemoPdfUpload(string $name = 'approval-memo.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }
}
