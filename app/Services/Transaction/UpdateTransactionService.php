<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;

class UpdateTransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    /**
     * @param  array{department_id: int, category_id: int, type: string, title: string, amount: mixed, description?: ?string, transaction_date: string}  $data
     */
    public function handle(Transaction $transaction, array $data): Transaction
    {
        return $this->transactionRepository->update($transaction, $data);
    }
}
