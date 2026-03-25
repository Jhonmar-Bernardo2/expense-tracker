<?php

namespace App\Enums;

enum ApprovalVoucherAttachmentKind: string
{
    case SupportingDocument = 'supporting_document';
    case ApprovalMemoPdf = 'approval_memo_pdf';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
