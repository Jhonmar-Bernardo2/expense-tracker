<?php

namespace App\Enums;

enum ApprovalMemoAttachmentKind: string
{
    case RequestSupport = 'request_support';
    case ApprovedMemo = 'approved_memo';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
