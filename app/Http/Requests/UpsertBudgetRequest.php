<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Services\Budget\BudgetAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(BudgetAccessService::class)->canManageCategoryBudgets($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('type', TransactionType::Expense->value)),
            ],
            'month' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'year' => [
                'required',
                'integer',
                'between:1900,2100',
            ],
            'amount_limit' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $departmentId = app(BudgetAccessService::class)->resolveBudgetDepartmentId();
            $budgetId = $this->route('budget');
            $ignoreBudgetId = is_numeric($budgetId) ? (int) $budgetId : null;
            $budgetRepository = app(BudgetRepository::class);

            if ($budgetRepository->existsActiveConflict(
                $departmentId,
                $this->integer('category_id'),
                $this->integer('month'),
                $this->integer('year'),
                $ignoreBudgetId > 0 ? $ignoreBudgetId : null,
            )) {
                $validator->errors()->add(
                    'category_id',
                    'A budget already exists for this category and month.',
                );
            }

            $approvedAllocation = app(BudgetAllocationRepository::class)->getActiveForPeriod(
                $departmentId,
                $this->integer('month'),
                $this->integer('year'),
            );

            if ($approvedAllocation === null) {
                $validator->errors()->add(
                    'amount_limit',
                    'An approved monthly total allocation is required before category budgets can be managed.',
                );

                return;
            }

            $currentTotal = $budgetRepository->sumActiveAmountLimitForPeriod(
                $departmentId,
                $this->integer('month'),
                $this->integer('year'),
                $ignoreBudgetId > 0 ? $ignoreBudgetId : null,
            );
            $proposedTotal = round($currentTotal + (float) $this->input('amount_limit'), 2);

            if ($proposedTotal > round((float) $approvedAllocation->amount_limit, 2)) {
                $validator->errors()->add(
                    'amount_limit',
                    'Category budgets cannot exceed the approved monthly total allocation.',
                );
            }
        });
    }
}
