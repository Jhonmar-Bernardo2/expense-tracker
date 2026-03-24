<?php

namespace App\Http\Requests;

use App\Enums\ApprovalVoucherAction;
use App\Enums\ApprovalVoucherModule;
use App\Enums\ApprovalVoucherStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexApprovalVoucherRequest extends FormRequest
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
            'department' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'module' => [
                'nullable',
                Rule::enum(ApprovalVoucherModule::class),
            ],
            'action' => [
                'nullable',
                Rule::enum(ApprovalVoucherAction::class),
            ],
            'status' => [
                'nullable',
                Rule::enum(ApprovalVoucherStatus::class),
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
