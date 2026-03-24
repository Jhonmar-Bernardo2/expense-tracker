<?php

namespace App\Http\Requests;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVoucherRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(VoucherStatus::class)],
            'type' => ['nullable', Rule::enum(VoucherType::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
