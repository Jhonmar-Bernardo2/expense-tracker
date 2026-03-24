<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalVoucherRequest extends FormRequest
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
            'rejection_reason' => [
                'required',
                'string',
            ],
        ];
    }
}
