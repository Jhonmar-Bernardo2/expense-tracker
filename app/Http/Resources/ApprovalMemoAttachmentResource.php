<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApprovalMemoAttachment */
class ApprovalMemoAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'uploaded_at' => $this->created_at?->toDateTimeString(),
            'download_url' => route('approval-memos.attachments.download', [
                'approvalMemo' => $this->approval_memo_id,
                'attachment' => $this->id,
            ]),
        ];
    }
}
