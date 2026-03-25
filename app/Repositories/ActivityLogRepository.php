<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\ApprovalMemo;
use App\Models\ApprovalVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogRepository
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function createForApprovalVoucher(
        ApprovalVoucher $approvalVoucher,
        ?User $actor,
        string $event,
        string $summary,
        array $meta = [],
    ): ActivityLog {
        return ActivityLog::query()->create([
            'actor_id' => $actor?->id,
            'department_id' => $approvalVoucher->department_id,
            'subject_type' => $approvalVoucher->getMorphClass(),
            'subject_id' => $approvalVoucher->getKey(),
            'event' => $event,
            'summary' => $summary,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function createForApprovalMemo(
        ApprovalMemo $approvalMemo,
        ?User $actor,
        string $event,
        string $summary,
        array $meta = [],
    ): ActivityLog {
        return ActivityLog::query()->create([
            'actor_id' => $actor?->id,
            'department_id' => $approvalMemo->department_id,
            'subject_type' => $approvalMemo->getMorphClass(),
            'subject_id' => $approvalMemo->getKey(),
            'event' => $event,
            'summary' => $summary,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getTimelineForApprovalVoucher(ApprovalVoucher $approvalVoucher): Collection
    {
        return ActivityLog::query()
            ->with('actor:id,name,email')
            ->where('subject_type', $approvalVoucher->getMorphClass())
            ->where('subject_id', $approvalVoucher->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getTimelineForApprovalMemo(ApprovalMemo $approvalMemo): Collection
    {
        return ActivityLog::query()
            ->with('actor:id,name,email')
            ->where('subject_type', $approvalMemo->getMorphClass())
            ->where('subject_id', $approvalMemo->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
