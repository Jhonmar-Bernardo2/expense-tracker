<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectVoucherService
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
        if (! $voucher->canReject($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only submitted vouchers can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $fromStatus = $voucher->status;
            $remarks = trim($data['remarks']);

            $voucher->update([
                'status' => VoucherStatus::Rejected->value,
                'approved_by' => null,
                'approved_amount' => null,
                'approved_at' => null,
                'rejection_reason' => $remarks,
                'rejected_at' => now(),
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'rejected',
                $fromStatus,
                VoucherStatus::Rejected,
                $remarks,
            );

            return $voucher->refresh();
        });
    }
}
