<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransactionType;
use App\Repositories\BudgetRepository;
use App\Services\Budget\BudgetAccessService;
use App\Services\Budget\BudgetAllocationSummaryService;
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

            $validationMessage = app(BudgetAllocationSummaryService::class)->getCategoryBudgetValidationMessage(
                $departmentId,
                $this->integer('month'),
                $this->integer('year'),
                (float) $this->input('amount_limit'),
                $ignoreBudgetId > 0 ? $ignoreBudgetId : null,
            );

            if ($validationMessage !== null) {
                $validator->errors()->add(
                    'amount_limit',
                    $validationMessage,
                );
            }
        });
    }
}
