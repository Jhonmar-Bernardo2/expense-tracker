<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'numeric', 'min:0.01'],
            'liquidation_due_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
