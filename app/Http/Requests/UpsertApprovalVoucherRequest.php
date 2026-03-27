<?php

namespace App\Http\Requests;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherAttachmentKind;
use App\Enums\ApprovalVoucherModule;
use App\Enums\TransactionType;
use App\Models\ApprovalVoucherAttachment;
use App\Models\BudgetAllocation;
use App\Repositories\BudgetAllocationRepository;
use App\Repositories\BudgetRepository;
use App\Services\Budget\BudgetAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertApprovalVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $module = ApprovalVoucherModule::tryFrom((string) $this->input('module'));

        if ($module === ApprovalVoucherModule::Transaction) {
            return ! $this->user()->isAdmin();
        }

        if ($module === ApprovalVoucherModule::Allocation) {
            return app(BudgetAccessService::class)->canRequestAllocations($this->user());
        }

        if ($module === ApprovalVoucherModule::Budget) {
            return $this->route('approvalVoucher') !== null
                && $this->user()->canManageCentralBudget();
        }

        return true;
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
        $requiresDepartment = $requiresPayload && $module === ApprovalVoucherModule::Transaction;
        $targetTable = match ($module) {
            ApprovalVoucherModule::Budget => 'budgets',
            ApprovalVoucherModule::Allocation => 'budget_allocations',
            default => 'transactions',
        };

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
            'attachments' => [
                'sometimes',
                'array',
                'max:5',
            ],
            'attachments.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:10240',
            ],
            'remove_attachment_ids' => [
                'sometimes',
                'array',
            ],
            'remove_attachment_ids.*' => [
                'integer',
                Rule::exists('approval_voucher_attachments', 'id'),
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

        if ($module === ApprovalVoucherModule::Allocation && $requiresPayload) {
            return array_merge($rules, [
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

            if ($module === ApprovalVoucherModule::Budget && $action !== ApprovalVoucherAction::Delete) {
                $departmentId = app(BudgetAccessService::class)->resolveBudgetDepartmentId();
                $ignoreBudgetId = $action === ApprovalVoucherAction::Update
                    ? $this->integer('target_id')
                    : null;

                if ($departmentId !== 0) {
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
                            'An active budget already exists for this category and month.',
                        );
                    }

                    $allocationRepository = app(BudgetAllocationRepository::class);
                    $approvedAllocation = $allocationRepository->getActiveForPeriod(
                        $departmentId,
                        $this->integer('month'),
                        $this->integer('year'),
                    );

                    if ($approvedAllocation === null) {
                        $validator->errors()->add(
                            'amount_limit',
                            'An approved monthly total allocation is required before category budgets can be managed.',
                        );
                    } else {
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
                    }
                }
            }

            if ($module === ApprovalVoucherModule::Allocation && $action !== ApprovalVoucherAction::Delete) {
                $departmentId = app(BudgetAccessService::class)->resolveBudgetDepartmentId();
                $ignoreAllocationId = $action === ApprovalVoucherAction::Update
                    ? $this->integer('target_id')
                    : null;

                $allocationRepository = app(BudgetAllocationRepository::class);

                if ($allocationRepository->existsActiveConflict(
                    $departmentId,
                    $this->integer('month'),
                    $this->integer('year'),
                    $ignoreAllocationId > 0 ? $ignoreAllocationId : null,
                )) {
                    $validator->errors()->add(
                        'month',
                        'An active total allocation already exists for this month and year.',
                    );
                }

                $currentAllocated = app(BudgetRepository::class)->sumActiveAmountLimitForPeriod(
                    $departmentId,
                    $this->integer('month'),
                    $this->integer('year'),
                );

                if (round((float) $this->input('amount_limit'), 2) < $currentAllocated) {
                    $validator->errors()->add(
                        'amount_limit',
                        'The total allocation cannot be lower than the active category budgets for this month.',
                    );
                }
            }

            if ($module === ApprovalVoucherModule::Allocation && $action === ApprovalVoucherAction::Delete) {
                $allocation = BudgetAllocation::query()
                    ->active()
                    ->find($this->integer('target_id'));

                if ($allocation !== null) {
                    $currentAllocated = app(BudgetRepository::class)->sumActiveAmountLimitForPeriod(
                        (int) $allocation->department_id,
                        (int) $allocation->month,
                        (int) $allocation->year,
                    );

                    if ($currentAllocated > 0) {
                        $validator->errors()->add(
                            'target_id',
                            'Remove the active category budgets for this month before deleting the total allocation.',
                        );
                    }
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateAttachmentRules($validator);
        });
    }

    private function validateAttachmentRules(Validator $validator): void
    {
        $approvalVoucherId = (int) $this->route('approvalVoucher', 0);
        $removeAttachmentIds = collect($this->input('remove_attachment_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($removeAttachmentIds->isNotEmpty() && $approvalVoucherId === 0) {
            $validator->errors()->add(
                'remove_attachment_ids',
                'Attachments can only be removed from an existing voucher.',
            );

            return;
        }

        if ($removeAttachmentIds->isNotEmpty()) {
            $matchedAttachmentCount = ApprovalVoucherAttachment::query()
                ->where('approval_voucher_id', $approvalVoucherId)
                ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
                ->whereIn('id', $removeAttachmentIds)
                ->count();

            if ($matchedAttachmentCount !== $removeAttachmentIds->count()) {
                $validator->errors()->add(
                    'remove_attachment_ids',
                    'One or more attachments could not be found for this voucher.',
                );

                return;
            }
        }

        $existingAttachmentCount = $approvalVoucherId > 0
            ? ApprovalVoucherAttachment::query()
                ->where('approval_voucher_id', $approvalVoucherId)
                ->where('kind', ApprovalVoucherAttachmentKind::SupportingDocument->value)
                ->when(
                    $removeAttachmentIds->isNotEmpty(),
                    fn ($query) => $query->whereKeyNot($removeAttachmentIds->all()),
                )
                ->count()
            : 0;

        $newAttachmentCount = count(array_filter(
            $this->file('attachments', []),
            fn ($file) => $file instanceof UploadedFile,
        ));

        if ($existingAttachmentCount + $newAttachmentCount > 5) {
            $validator->errors()->add(
                'attachments',
                'A voucher can only have up to 5 supporting documents.',
            );
        }
    }
}
