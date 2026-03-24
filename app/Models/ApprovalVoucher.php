<?php

namespace App\Models;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function isRequestedBy(User $user): bool
    {
        return $this->requested_by === $user->id;
    }

    public function canEditRequest(User $user): bool
    {
        return $this->isRequestedBy($user)
            && in_array($this->status, [ApprovalVoucherStatus::Draft, ApprovalVoucherStatus::Rejected], true);
    }

    public function canSubmitRequest(User $user): bool
    {
        return $this->canEditRequest($user);
    }

    public function canApprove(User $user): bool
    {
        return $user->isAdmin() && $this->status === ApprovalVoucherStatus::PendingApproval;
    }

    public function canReject(User $user): bool
    {
        return $user->isAdmin() && $this->status === ApprovalVoucherStatus::PendingApproval;
    }
}
