<?php

namespace App\Services\Department;

use App\Models\Department;
use App\Repositories\DepartmentRepository;

class DeleteDepartmentService
{
    public function __construct(
        private readonly DepartmentRepository $departmentRepository,
    ) {}

    public function handle(Department $department): ?string
    {
        if ($department->isLocked()) {
            return 'The Finance Team department is protected and cannot be deleted.';
        }

        if ($this->departmentRepository->hasUsers($department)) {
            return 'This department cannot be deleted because it still has assigned users or financial records.';
        }

        $this->departmentRepository->delete($department);

        return null;
    }
}
