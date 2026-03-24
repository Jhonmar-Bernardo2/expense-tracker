<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitLiquidationService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
    ) {
    }

    /**
     * @param  array{
     *     remarks?: ?string,
     *     items: array<int, array{category_id: int, description: string, amount: mixed, expense_date: string}>,
     *     attachments?: array<int, UploadedFile>
     * }  $data
     */
    public function handle(User $actor, Voucher $voucher, array $data): Voucher
    {
        if (! $voucher->canSubmitLiquidation($actor)) {
            throw ValidationException::withMessages([
                'voucher' => 'This voucher is not ready for liquidation.',
            ]);
        }

        $items = collect($data['items'])->map(fn (array $item) => [
            'category_id' => (int) $item['category_id'],
            'description' => trim($item['description']),
            'amount' => (float) $item['amount'],
            'expense_date' => $item['expense_date'],
        ]);

        $liquidationTotal = $items->sum('amount');

        if ($liquidationTotal <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Liquidation items must total more than zero.',
            ]);
        }

        if ($voucher->released_amount !== null && $liquidationTotal > (float) $voucher->released_amount) {
            throw ValidationException::withMessages([
                'items' => 'Liquidation total cannot exceed the released amount.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher, $items, $data): Voucher {
            $fromStatus = $voucher->status;

            $voucher->items()->delete();
            $voucher->items()->createMany($items->map(fn (array $item) => [
                'category_id' => $item['category_id'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'expense_date' => $item['expense_date'],
            ])->all());

            foreach ($data['attachments'] ?? [] as $attachment) {
                $path = $attachment->store("vouchers/{$voucher->id}", 'local');

                $voucher->attachments()->create([
                    'uploaded_by' => $actor->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $attachment->getClientOriginalName(),
                    'mime_type' => $attachment->getClientMimeType(),
                    'size' => $attachment->getSize() ?? 0,
                ]);
            }

            $voucher->update([
                'status' => VoucherStatus::LiquidationSubmitted->value,
                'liquidation_submitted_at' => now(),
                'liquidation_return_reason' => null,
            ]);

            $this->voucherRepository->createLog(
                $voucher,
                $actor,
                'liquidation_submitted',
                $fromStatus,
                VoucherStatus::LiquidationSubmitted,
                $data['remarks'] ?? null,
            );

            return $voucher->refresh();
        });
    }
}
