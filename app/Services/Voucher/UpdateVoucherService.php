<?php

namespace App\Services\Voucher;

use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateVoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{type: string, purpose: string, requested_amount: mixed, remarks?: ?string}  $data
     */
    public function handle(User $actor, Voucher $voucher, int $departmentId, array $data): Voucher
    {
        if (! $voucher->canEditRequest($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only draft or rejected vouchers can be updated.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $departmentId, $data): Voucher {
            $voucher->update([
                'department_id' => $departmentId,
                'type' => $data['type'],
                'purpose' => trim($data['purpose']),
                'remarks' => $data['remarks'] ?? null,
                'requested_amount' => $data['requested_amount'],
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'updated',
                $voucher->status,
                $voucher->status,
                'Voucher request details updated.',
            );

            return $voucher->refresh();
        });
    }
}
