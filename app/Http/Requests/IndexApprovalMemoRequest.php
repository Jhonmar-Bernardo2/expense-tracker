<?php

namespace App\Http\Requests;

use App\Enums\ApprovalMemoAction;
use App\Enums\ApprovalMemoStatus;
use App\Enums\ApprovalVoucherModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexApprovalMemoRequest extends FormRequest
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
            'department' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'status' => ['nullable', Rule::enum(ApprovalMemoStatus::class)],
            'module' => ['nullable', Rule::enum(ApprovalVoucherModule::class)],
            'action' => ['nullable', Rule::enum(ApprovalMemoAction::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
