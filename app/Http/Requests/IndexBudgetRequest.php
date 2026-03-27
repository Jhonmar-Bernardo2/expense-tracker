<?php

namespace App\Http\Requests;

use App\Services\Budget\BudgetAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(BudgetAccessService::class)->canViewPage($this->user());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'department' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
        ];
    }
}
