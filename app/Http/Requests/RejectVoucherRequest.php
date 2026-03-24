<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectVoucherRequest extends FormRequest
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
            'remarks' => ['required', 'string'],
        ];
    }
}
