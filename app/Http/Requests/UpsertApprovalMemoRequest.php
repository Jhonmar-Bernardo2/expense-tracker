<?php

namespace App\Http\Requests;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalVoucherModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertApprovalMemoRequest extends FormRequest
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
        return [
            'module' => [
                'required',
                Rule::enum(ApprovalVoucherModule::class),
            ],
            'action' => [
                'required',
                Rule::enum(ApprovalMemoAction::class),
            ],
            'department_id' => [
                $this->user()?->isAdmin() ? 'required' : 'nullable',
                'integer',
                Rule::exists('departments', 'id'),
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
    }
}
