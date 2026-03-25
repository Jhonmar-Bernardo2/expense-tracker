<?php

namespace App\Models;

use App\Enums\ApprovalVoucherAttachmentKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalVoucherAttachment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'approval_voucher_id',
        'uploaded_by',
        'kind',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ApprovalVoucherAttachmentKind::class,
            'size_bytes' => 'integer',
        ];
    }

    public function approvalVoucher(): BelongsTo
    {
        return $this->belongsTo(ApprovalVoucher::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
