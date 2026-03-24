<?php

namespace App\Services\Department;

use App\Models\Department;
use App\Models\User;
use App\Repositories\DepartmentRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DepartmentScopeService
{
    public function __construct(
        private readonly DepartmentRepository $departmentRepository,
    ) {
    }

    /**
     * @return array{
     *     department_id: int|null,
     *     selected_department: array{id: int, name: string}|null,
     *     can_select_department: bool,
     *     is_all_departments: bool
     * }
     */
    public function resolveFilterScope(User $user, ?int $requestedDepartmentId): array
    {
        if ($user->isAdmin()) {
            $department = $requestedDepartmentId === null
                ? null
                : $this->departmentRepository->findOrFail($requestedDepartmentId);

            return $this->formatScope($department, true);
        }

        $department = $this->resolveUsersDepartment($user);

        return $this->formatScope($department, false);
    }

    public function resolveWritableDepartmentId(User $user, ?int $requestedDepartmentId): int
    {
        if ($user->isAdmin()) {
            if ($requestedDepartmentId === null) {
                throw ValidationException::withMessages([
                    'department_id' => 'Please select a department.',
                ]);
            }

            return $this->departmentRepository->findOrFail($requestedDepartmentId)->id;
        }

        return $this->resolveUsersDepartment($user)->id;
    }

    /**
     * @return Collection<int, Department>
     */
    public function getOptionsFor(User $user): Collection
    {
        if ($user->isAdmin()) {
            return $this->departmentRepository->getOptions();
        }

        return collect([$this->resolveUsersDepartment($user)]);
    }

    private function resolveUsersDepartment(User $user): Department
    {
        if ($user->relationLoaded('department') && $user->department !== null) {
            return $user->department;
        }

        return $this->departmentRepository->findOrFail((int) $user->department_id);
    }

    /**
     * @return array{
     *     department_id: int|null,
     *     selected_department: array{id: int, name: string}|null,
     *     can_select_department: bool,
     *     is_all_departments: bool
     * }
     */
    private function formatScope(?Department $department, bool $canSelectDepartment): array
    {
        return [
            'department_id' => $department?->id,
            'selected_department' => $department === null
                ? null
                : [
                    'id' => $department->id,
                    'name' => $department->name,
                ],
            'can_select_department' => $canSelectDepartment,
            'is_all_departments' => $canSelectDepartment && $department === null,
        ];
    }
}
