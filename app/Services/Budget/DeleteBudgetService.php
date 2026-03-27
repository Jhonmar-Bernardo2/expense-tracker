<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Repositories\BudgetRepository;

class DeleteBudgetService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
    ) {}

    public function handle(Budget $budget): Budget
    {
        return $this->budgetRepository->archive($budget);
    }
}
