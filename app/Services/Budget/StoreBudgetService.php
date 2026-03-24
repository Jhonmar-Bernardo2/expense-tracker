<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\User;
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
    public function handle(User $user, int $departmentId, array $data): Budget
    {
        return $this->budgetRepository->create($user, $departmentId, $data);
    }
}
