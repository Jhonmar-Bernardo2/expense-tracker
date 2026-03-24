<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;

class StoreTransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    /**
     * @param  array{category_id: int, type: string, title: string, amount: mixed, description?: ?string, transaction_date: string}  $data
     */
    public function handle(User $user, int $departmentId, array $data): Transaction
    {
        return $this->transactionRepository->create($user, $departmentId, $data);
    }
}
