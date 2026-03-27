<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAllocation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'department_id',
        'origin_approval_voucher_id',
        'month',
        'year',
        'amount_limit',
        'archived_at',
        'archived_by_approval_voucher_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'amount_limit' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function originApprovalVoucher(): BelongsTo
    {
        return $this->belongsTo(ApprovalVoucher::class, 'origin_approval_voucher_id');
    }

    public function archivedByApprovalVoucher(): BelongsTo
    {
        return $this->belongsTo(ApprovalVoucher::class, 'archived_by_approval_voucher_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
