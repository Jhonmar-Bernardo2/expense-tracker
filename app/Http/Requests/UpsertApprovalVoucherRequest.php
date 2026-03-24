<?php

namespace App\Http\Requests;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\TransactionType;
use App\Repositories\BudgetRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertApprovalVoucherRequest extends FormRequest
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
        $module = ApprovalVoucherModule::tryFrom((string) $this->input('module'));
        $action = ApprovalVoucherAction::tryFrom((string) $this->input('action'));
        $requiresTarget = in_array($action, [ApprovalVoucherAction::Update, ApprovalVoucherAction::Delete], true);
        $requiresPayload = $action !== ApprovalVoucherAction::Delete;
        $requiresDepartment = $requiresPayload;
        $targetTable = $module === ApprovalVoucherModule::Budget ? 'budgets' : 'transactions';

        $rules = [
            'module' => [
                'required',
                Rule::enum(ApprovalVoucherModule::class),
            ],
            'action' => [
                'required',
                Rule::enum(ApprovalVoucherAction::class),
            ],
            'department_id' => [
                $this->user()?->isAdmin() && $requiresDepartment ? 'required' : 'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'target_id' => [
                $requiresTarget ? 'required' : 'nullable',
                'integer',
                Rule::exists($targetTable, 'id'),
            ],
            'remarks' => [
                'nullable',
                'string',
            ],
            'auto_submit' => [
                'sometimes',
                'boolean',
            ],
        ];

        if ($module === ApprovalVoucherModule::Transaction && $requiresPayload) {
            return array_merge($rules, [
                'type' => [
                    'required',
                    Rule::enum(TransactionType::class),
                ],
                'category_id' => [
                    'required',
                    'integer',
                    Rule::exists('categories', 'id')
                        ->where(fn ($query) => $query->where('type', $this->input('type'))),
                ],
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],
                'description' => [
                    'nullable',
                    'string',
                ],
                'transaction_date' => [
                    'required',
                    'date',
                ],
            ]);
        }

        if ($module === ApprovalVoucherModule::Budget && $requiresPayload) {
            return array_merge($rules, [
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
            ]);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $module = ApprovalVoucherModule::tryFrom((string) $this->input('module'));
            $action = ApprovalVoucherAction::tryFrom((string) $this->input('action'));

            if ($module !== ApprovalVoucherModule::Budget || $action === ApprovalVoucherAction::Delete) {
                return;
            }

            $departmentId = $this->user()?->isAdmin()
                ? $this->integer('department_id')
                : (int) $this->user()?->department_id;
            $ignoreBudgetId = $action === ApprovalVoucherAction::Update
                ? $this->integer('target_id')
                : null;

            if ($departmentId === 0) {
                return;
            }

            $hasConflict = app(BudgetRepository::class)->existsActiveConflict(
                $departmentId,
                $this->integer('category_id'),
                $this->integer('month'),
                $this->integer('year'),
                $ignoreBudgetId > 0 ? $ignoreBudgetId : null,
            );

            if (! $hasConflict) {
                return;
            }

            $validator->errors()->add(
                'category_id',
                'An active budget already exists for this category and month.',
            );
        });
    }
}
