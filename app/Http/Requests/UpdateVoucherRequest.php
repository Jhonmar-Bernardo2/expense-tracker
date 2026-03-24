<?php

namespace App\Http\Requests;

use App\Enums\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVoucherRequest extends FormRequest
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
            'department_id' => [
                $this->user()?->isAdmin() ? 'required' : 'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'type' => [
                'required',
                Rule::enum(VoucherType::class),
            ],
            'purpose' => [
                'required',
                'string',
                'max:255',
            ],
            'requested_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'remarks' => [
                'nullable',
                'string',
            ],
        ];
    }
}
