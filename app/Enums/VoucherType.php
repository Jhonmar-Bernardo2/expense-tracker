<?php

namespace App\Enums;

enum VoucherType: string
{
    case CashAdvance = 'cash_advance';
    case Reimbursement = 'reimbursement';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
