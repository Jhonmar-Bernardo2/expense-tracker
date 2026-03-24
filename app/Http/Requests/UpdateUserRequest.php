<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules((int) $this->route('user')),
            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],
            'department_id' => [
                'required',
                'integer',
                'exists:departments,id',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}
