<?php

namespace App\Enums;

enum ApprovalVoucherModule: string
{
    case Transaction = 'transaction';
    case Budget = 'budget';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
