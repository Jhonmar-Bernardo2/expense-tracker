<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Repositories\BudgetRepository;

class UpdateBudgetService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
    ) {
    }

    /**
     * @param  array{department_id: int, category_id: int, month: int, year: int, amount_limit: mixed}  $data
     */
    public function handle(Budget $budget, array $data): Budget
    {
        return $this->budgetRepository->update($budget, $data);
    }
}
