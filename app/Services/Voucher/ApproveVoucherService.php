<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveVoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{approved_amount: mixed, liquidation_due_date?: ?string, remarks?: ?string}  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data): Voucher
    {
        if (! $voucher->canApprove($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only submitted vouchers can be approved.',
            ]);
        }

        if ((float) $data['approved_amount'] > (float) $voucher->requested_amount) {
            throw ValidationException::withMessages([
                'approved_amount' => 'Approved amount cannot exceed the requested amount.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $fromStatus = $voucher->status;

            $voucher->update([
                'status' => VoucherStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_amount' => $data['approved_amount'],
                'liquidation_due_date' => $data['liquidation_due_date'] ?? null,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'approved',
                $fromStatus,
                VoucherStatus::Approved,
                $data['remarks'] ?? null,
            );

            return $voucher->refresh();
        });
    }
}
