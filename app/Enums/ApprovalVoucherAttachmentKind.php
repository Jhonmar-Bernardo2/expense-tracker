<?php

namespace App\Enums;

enum ApprovalVoucherAttachmentKind: string
{
    case SupportingDocument = 'supporting_document';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
