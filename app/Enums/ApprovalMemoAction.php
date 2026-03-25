<?php

namespace App\Enums;

enum ApprovalMemoAction: string
{
    case Create = 'create';
    case Update = 'update';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
