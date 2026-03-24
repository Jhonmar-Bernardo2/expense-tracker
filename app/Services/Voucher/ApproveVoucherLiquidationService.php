<?php

namespace App\Services\Voucher;

use App\Enums\TransactionType;
use App\Enums\VoucherStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Repositories\VoucherRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveVoucherLiquidationService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{remarks?: ?string}  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data): Voucher
    {
        if (! $voucher->canApproveLiquidation($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'Only submitted liquidations can be approved.',
            ]);
        }

        $voucher->loadMissing('items');

        if ($voucher->items->isEmpty()) {
            throw ValidationException::withMessages([
                'voucher' => 'A liquidation submission must include at least one expense item.',
            ]);
        }

        if ($voucher->transactions()->exists()) {
            throw ValidationException::withMessages([
                'voucher' => 'This voucher liquidation has already been posted.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $data): Voucher {
            $fromStatus = $voucher->status;
            $purpose = str($voucher->purpose)->limit(200)->toString();

            /** @var VoucherItem $item */
            foreach ($voucher->items as $item) {
                Transaction::query()->create([
                    'user_id' => $voucher->requested_by,
                    'department_id' => $voucher->department_id,
                    'voucher_id' => $voucher->id,
                    'category_id' => $item->category_id,
                    'type' => TransactionType::Expense->value,
                    'title' => str($item->description)->limit(255)->toString(),
                    'amount' => $item->amount,
                    'description' => "Liquidated via {$voucher->voucher_no}. {$purpose}",
                    'transaction_date' => $item->expense_date?->toDateString(),
                ]);
            }

            $voucher->update([
                'status' => VoucherStatus::LiquidationApproved->value,
                'liquidation_reviewed_by' => $actor->id,
                'liquidation_reviewed_at' => now(),
                'posted_at' => now(),
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'liquidation_approved',
                $fromStatus,
                VoucherStatus::LiquidationApproved,
                $data['remarks'] ?? null,
            );

            return $voucher->refresh();
        });
    }
}
