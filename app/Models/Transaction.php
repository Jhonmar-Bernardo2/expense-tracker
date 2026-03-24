<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'department_id',
        'voucher_id',
        'origin_approval_voucher_id',
        'category_id',
        'type',
        'title',
        'amount',
        'description',
        'transaction_date',
        'voided_at',
        'voided_by_approval_voucher_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function originApprovalVoucher(): BelongsTo
    {
        return $this->belongsTo(ApprovalVoucher::class, 'origin_approval_voucher_id');
    }

    public function voidedByApprovalVoucher(): BelongsTo
    {
        return $this->belongsTo(ApprovalVoucher::class, 'voided_by_approval_voucher_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
