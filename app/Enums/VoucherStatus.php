<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Released = 'released';
    case LiquidationSubmitted = 'liquidation_submitted';
    case LiquidationReturned = 'liquidation_returned';
    case LiquidationApproved = 'liquidation_approved';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
