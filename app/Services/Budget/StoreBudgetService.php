<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Repositories\BudgetRepository;

class StoreBudgetService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
    ) {
    }

    /**
     * @param  array{category_id: int, month: int, year: int, amount_limit: mixed}  $data
     */
    public function handle(int $userId, array $data): Budget
    {
        return $this->budgetRepository->createForUser($userId, $data);
    }
}
