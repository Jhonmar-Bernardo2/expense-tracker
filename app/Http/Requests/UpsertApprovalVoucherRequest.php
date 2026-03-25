<?php

namespace App\Http\Requests;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherAttachmentKind;
use App\Enums\ApprovalVoucherModule;
use App\Enums\TransactionType;
use App\Models\ApprovalVoucherAttachment;
use App\Repositories\BudgetRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
            'approval_memo_id' => [
                'nullable',
                'integer',
                Rule::exists('approval_memos', 'id'),
            ],
            'approval_memo_pdf' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:10240',
            ],
            'remove_approval_memo_pdf' => [
                'sometimes',
                'boolean',
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

            if (
                $action === ApprovalVoucherAction::Delete
                && $this->filled('approval_memo_id')
            ) {
                $validator->errors()->add(
                    'approval_memo_id',
                    'Approval memo is only used for create and update requests.',
                );

                return;
            }

            if (
                $action === ApprovalVoucherAction::Delete
                && ($this->hasFile('approval_memo_pdf') || $this->boolean('remove_approval_memo_pdf'))
            ) {
                $validator->errors()->add(
                    'approval_memo_pdf',
                    'Approval memo PDF is only used for create and update requests.',
                );

                return;
            }

            if ($module === ApprovalVoucherModule::Budget && $action !== ApprovalVoucherAction::Delete) {
                $departmentId = $this->user()?->isAdmin()
                    ? $this->integer('department_id')
                    : (int) $this->user()?->department_id;
                $ignoreBudgetId = $action === ApprovalVoucherAction::Update
                    ? $this->integer('target_id')
                    : null;

                if ($departmentId !== 0) {
                    $hasConflict = app(BudgetRepository::class)->existsActiveConflict(
                        $departmentId,
                        $this->integer('category_id'),
                        $this->integer('month'),
                        $this->integer('year'),
                        $ignoreBudgetId > 0 ? $ignoreBudgetId : null,
                    );

                    if ($hasConflict) {
                        $validator->errors()->add(
                            'category_id',
                            'An active budget already exists for this category and month.',
                        );
                    }
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (
                $action !== ApprovalVoucherAction::Delete
                && (bool) $this->input('auto_submit', false)
            ) {
                if (
                    $module === ApprovalVoucherModule::Budget
                    && ! $this->filled('approval_memo_id')
                ) {
                    $validator->errors()->add(
                        'approval_memo_id',
                        'Select an approved memo before submitting this request.',
                    );
                }

                if (! $this->hasFile('approval_memo_pdf')) {
                    $validator->errors()->add(
                        'approval_memo_pdf',
                        'Upload the approval memo PDF before submitting this request.',
                    );
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
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
