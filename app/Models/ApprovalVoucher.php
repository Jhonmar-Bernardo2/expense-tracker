<?php

namespace App\Models;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherAttachmentKind;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApprovalVoucher extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'voucher_no',
        'department_id',
        'requested_by',
        'approved_by',
        'module',
        'action',
        'status',
        'target_id',
        'before_payload',
        'after_payload',
        'remarks',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => ApprovalVoucherModule::class,
            'action' => ApprovalVoucherAction::class,
            'status' => ApprovalVoucherStatus::class,
            'before_payload' => 'array',
            'after_payload' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ApprovalVoucherAttachment::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function supportingAttachments(): HasMany
    {
        return $this->hasMany(ApprovalVoucherAttachment::class)
            ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function isRequestedBy(User $user): bool
    {
        return $this->requested_by === $user->id;
    }

    public function canEditRequest(User $user): bool
    {
        if (! in_array($this->status, [ApprovalVoucherStatus::Draft, ApprovalVoucherStatus::Rejected], true)) {
            return false;
        }

        return $this->isRequestedBy($user)
            || $this->canCollaborateOnBudgetRequest($user);
    }

    public function canSubmitRequest(User $user): bool
    {
        return $this->canEditRequest($user);
    }

    public function canApprove(User $user): bool
    {
        if ($this->status !== ApprovalVoucherStatus::PendingApproval) {
            return false;
        }

        if ($this->isRequestedBy($user)) {
            return false;
        }

        return match ($this->module) {
            ApprovalVoucherModule::Transaction => $user->canApproveTransactionRequests(),
            ApprovalVoucherModule::Budget => $user->isAdmin(),
            ApprovalVoucherModule::Allocation => $user->canApproveBudgetAllocations(),
        };
    }

    public function canReject(User $user): bool
    {
        return $this->canApprove($user);
    }

    public function resolveSubject(): string
    {
        $payload = $this->after_payload ?? $this->before_payload ?? [];

        if ($this->module === ApprovalVoucherModule::Transaction) {
            return (string) ($payload['title'] ?? "Transaction #{$this->target_id}");
        }

        $month = isset($payload['month']) ? (int) $payload['month'] : null;
        $year = isset($payload['year']) ? (int) $payload['year'] : null;
        $monthLabel = $month !== null && $year !== null && $month >= 1 && $month <= 12
            ? sprintf('%s %d', date('F', mktime(0, 0, 0, $month, 1)), $year)
            : null;

        if ($this->module === ApprovalVoucherModule::Allocation) {
            return $monthLabel === null
                ? 'Monthly allocation request'
                : "Total allocation for {$monthLabel}";
        }

        if ($monthLabel !== null) {
            return "Category budget for {$monthLabel}";
        }

        return $this->target_id === null
            ? 'Budget request'
            : "Budget #{$this->target_id}";
    }

    public function pendingAgeDays(): ?int
    {
        if ($this->status !== ApprovalVoucherStatus::PendingApproval || $this->submitted_at === null) {
            return null;
        }

        return $this->submitted_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function isOverdue(): bool
    {
        $pendingAgeDays = $this->pendingAgeDays();

        return $pendingAgeDays !== null && $pendingAgeDays > 3;
    }

    private function canCollaborateOnBudgetRequest(User $user): bool
    {
        if (! in_array($this->module, [ApprovalVoucherModule::Budget, ApprovalVoucherModule::Allocation], true)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isFinancialManagement()
            && $this->department_id === $user->department_id;
    }
}
