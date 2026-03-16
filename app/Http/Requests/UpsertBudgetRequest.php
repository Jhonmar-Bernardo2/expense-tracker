<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $budgetId = $this->route('budget');

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('type', TransactionType::Expense->value)),
                Rule::unique('budgets')
                    ->where(fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('month', $this->input('month'))
                        ->where('year', $this->input('year')))
                    ->ignore($budgetId),
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.unique' => 'A budget already exists for this category and month.',
        ];
    }
}
