<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'description' => Str::of((string) $this->input('description'))->squish()->value() ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $departmentId = $this->route('department');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Department::class)->ignore($departmentId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
