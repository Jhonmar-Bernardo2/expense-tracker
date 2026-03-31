<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransactionType;
use App\Services\Budget\BudgetAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertCategoryBudgetPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(BudgetAccessService::class)->canManageCategoryBudgets($this->user());
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(fn ($item) => [
                'category_id' => $item['category_id'] ?? null,
                'amount_limit' => $item['amount_limit'] ?? null,
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $presetId = $this->route('categoryBudgetPreset');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_budget_presets', 'name')
                    ->ignore($presetId),
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.category_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('type', TransactionType::Expense->value)),
            ],
            'items.*.amount_limit' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }
}
