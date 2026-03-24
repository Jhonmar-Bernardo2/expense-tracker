<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseVoucherRequest extends FormRequest
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
            'released_amount' => ['nullable', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
