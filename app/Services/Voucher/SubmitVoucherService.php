<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitVoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{remarks?: ?string}  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data = []): Voucher
    {
        if (! $voucher->canSubmitRequest($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only draft or rejected vouchers can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $fromStatus = $voucher->status;

            $voucher->update([
                'status' => VoucherStatus::PendingApproval->value,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'submitted',
                $fromStatus,
                VoucherStatus::PendingApproval,
                $data['remarks'] ?? null,
            );

            return $voucher->refresh();
        });
    }
}
