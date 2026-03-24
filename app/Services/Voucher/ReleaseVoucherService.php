<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReleaseVoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{released_amount?: mixed, remarks?: ?string}  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data): Voucher
    {
        if (! $voucher->canRelease($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only approved vouchers can be released.',
            ]);
        }

        $approvedAmount = (float) ($voucher->approved_amount ?? 0);
        $releasedAmount = isset($data['released_amount'])
            ? (float) $data['released_amount']
            : $approvedAmount;

        if ($releasedAmount <= 0) {
            throw ValidationException::withMessages([
                'released_amount' => 'Released amount must be greater than zero.',
            ]);
        }

        if ($releasedAmount > $approvedAmount) {
            throw ValidationException::withMessages([
                'released_amount' => 'Released amount cannot exceed the approved amount.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $releasedAmount, $data): Voucher {
            $fromStatus = $voucher->status;

            $voucher->update([
                'status' => VoucherStatus::Released->value,
                'released_by' => $actor->id,
                'released_amount' => $releasedAmount,
                'released_at' => now(),
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'released',
                $fromStatus,
                VoucherStatus::Released,
                $data['remarks'] ?? null,
            );

            return $voucher->refresh();
        });
    }
}
