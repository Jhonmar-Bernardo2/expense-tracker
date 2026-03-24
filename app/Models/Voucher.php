<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
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
        'released_by',
        'liquidation_reviewed_by',
        'type',
        'status',
        'purpose',
        'remarks',
        'rejection_reason',
        'liquidation_return_reason',
        'requested_amount',
        'approved_amount',
        'released_amount',
        'liquidation_due_date',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'released_at',
        'liquidation_submitted_at',
        'liquidation_reviewed_at',
        'posted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'status' => VoucherStatus::class,
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
            'liquidation_due_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'released_at' => 'datetime',
            'liquidation_submitted_at' => 'datetime',
            'liquidation_reviewed_at' => 'datetime',
            'posted_at' => 'datetime',
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

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function liquidationReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liquidation_reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VoucherItem::class)->orderBy('expense_date')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(VoucherAttachment::class)->orderByDesc('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VoucherLog::class)->orderByDesc('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('transaction_date')->orderBy('id');
    }

    public function isRequestedBy(User $user): bool
    {
        return $this->requested_by === $user->id;
    }

    public function canEditRequest(User $user): bool
    {
        return ($user->isAdmin() || $this->isRequestedBy($user))
            && in_array($this->status, [VoucherStatus::Draft, VoucherStatus::Rejected], true);
    }

    public function canSubmitRequest(User $user): bool
    {
        return ($user->isAdmin() || $this->isRequestedBy($user))
            && in_array($this->status, [VoucherStatus::Draft, VoucherStatus::Rejected], true);
    }

    public function canApprove(User $user): bool
    {
        return $user->isAdmin() && $this->status === VoucherStatus::PendingApproval;
    }

    public function canReject(User $user): bool
    {
        return $user->isAdmin() && $this->status === VoucherStatus::PendingApproval;
    }

    public function canRelease(User $user): bool
    {
        return $user->isAdmin() && $this->status === VoucherStatus::Approved;
    }

    public function canSubmitLiquidation(User $user): bool
    {
        return ($user->isAdmin() || $this->isRequestedBy($user))
            && in_array($this->status, [VoucherStatus::Released, VoucherStatus::LiquidationReturned], true);
    }

    public function canReturnLiquidation(User $user): bool
    {
        return $user->isAdmin() && $this->status === VoucherStatus::LiquidationSubmitted;
    }

    public function canApproveLiquidation(User $user): bool
    {
        return $user->isAdmin() && $this->status === VoucherStatus::LiquidationSubmitted;
    }
}
