<?php

namespace App\Enums;

enum ApprovalVoucherAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
