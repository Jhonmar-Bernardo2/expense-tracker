<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnVoucherLiquidationService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{remarks: string}  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data): Voucher
    {
        if (! $voucher->canReturnLiquidation($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only submitted liquidations can be returned.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $fromStatus = $voucher->status;
            $remarks = trim($data['remarks']);

            $voucher->update([
                'status' => VoucherStatus::LiquidationReturned->value,
                'liquidation_reviewed_by' => $actor->id,
                'liquidation_reviewed_at' => now(),
                'liquidation_return_reason' => $remarks,
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'liquidation_returned',
                $fromStatus,
                VoucherStatus::LiquidationReturned,
                $remarks,
            );

            return $voucher->refresh();
        });
    }
}
