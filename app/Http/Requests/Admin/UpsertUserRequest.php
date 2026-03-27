<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user');
        $isUpdate = is_numeric($userId);
        $passwordRules = $this->passwordRules();

        if ($isUpdate) {
            $passwordRules = array_values(array_filter(
                $passwordRules,
                fn ($rule) => $rule !== 'required',
            ));
            array_unshift($passwordRules, 'nullable');
        }

        return [
            ...$this->profileRules($isUpdate ? (int) $userId : null),
            'password' => $passwordRules,
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
                $isUpdate ? 'required' : 'nullable',
                'boolean',
            ],
        ];
    }
}
