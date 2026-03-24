<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;

class StoreVoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{type: string, purpose: string, requested_amount: mixed, remarks?: ?string}  $data
     */
    public function handle(User $user, int $departmentId, array $data): Voucher
    {
        return DB::transaction(function () use ($user, $departmentId, $data): Voucher {
            $voucher = Voucher::query()->create([
                'voucher_no' => 'PENDING',
                'department_id' => $departmentId,
                'requested_by' => $user->id,
                'type' => $data['type'],
                'status' => VoucherStatus::Draft->value,
                'purpose' => trim($data['purpose']),
                'remarks' => $data['remarks'] ?? null,
                'requested_amount' => $data['requested_amount'],
            ]);

            $voucher->update([
                'voucher_no' => $this->voucherRepository->formatVoucherNumber($voucher),
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $user,
                'created',
                null,
                VoucherStatus::Draft,
                $data['remarks'] ?? null,
            );

            return $voucher->refresh();
        });
    }
}
