<?php

namespace App\Models;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoAttachmentKind;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ApprovalMemo extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'memo_no',
        'department_id',
        'requested_by',
        'approved_by',
        'module',
        'action',
        'status',
        'remarks',
        'admin_remarks',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => ApprovalVoucherModule::class,
            'action' => ApprovalMemoAction::class,
            'status' => ApprovalMemoStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(ApprovalMemoAttachment::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function requestSupportAttachments(): HasMany
    {
        return $this->hasMany(ApprovalMemoAttachment::class)
            ->where('kind', ApprovalMemoAttachmentKind::RequestSupport->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function approvedMemoAttachment(): HasOne
    {
        return $this->hasOne(ApprovalMemoAttachment::class)
            ->where('kind', ApprovalMemoAttachmentKind::ApprovedMemo->value)
            ->latestOfMany();
    }

    public function linkedApprovalVoucher(): HasOne
    {
        return $this->hasOne(ApprovalVoucher::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function isRequestedBy(User $user): bool
    {
        return $this->requested_by === $user->id;
    }

    public function canEditRequest(User $user): bool
    {
        return $this->isRequestedBy($user)
            && in_array($this->status, [ApprovalMemoStatus::Draft, ApprovalMemoStatus::Rejected], true);
    }

    public function canSubmitRequest(User $user): bool
    {
        return $this->canEditRequest($user);
    }

    public function canApprove(User $user): bool
    {
        return $user->isAdmin() && $this->status === ApprovalMemoStatus::PendingApproval;
    }

    public function canReject(User $user): bool
    {
        return $user->isAdmin() && $this->status === ApprovalMemoStatus::PendingApproval;
    }

    public function canDeleteRequest(User $user): bool
    {
        return $this->isRequestedBy($user)
            && in_array($this->status, [ApprovalMemoStatus::Draft, ApprovalMemoStatus::Rejected], true)
            && ! $this->hasLinkedApprovalVoucher();
    }

    public function canPrint(User $user): bool
    {
        return $this->status === ApprovalMemoStatus::Approved
            && ($user->isAdmin() || $this->isRequestedBy($user));
    }

    public function hasLinkedApprovalVoucher(): bool
    {
        if ($this->relationLoaded('linkedApprovalVoucher')) {
            return $this->linkedApprovalVoucher !== null;
        }

        return $this->linkedApprovalVoucher()->exists();
    }

    public function resolveSubject(): string
    {
        return sprintf(
            '%s %s memo',
            $this->module->label(),
            $this->action->label(),
        );
    }
}
