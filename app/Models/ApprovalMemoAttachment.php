<?php

namespace App\Models;

use App\Enums\ApprovalMemoAttachmentKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalMemoAttachment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'approval_memo_id',
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
            'kind' => ApprovalMemoAttachmentKind::class,
            'size_bytes' => 'integer',
        ];
    }

    public function approvalMemo(): BelongsTo
    {
        return $this->belongsTo(ApprovalMemo::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
