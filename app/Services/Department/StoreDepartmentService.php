<?php

namespace App\Services\Department;

use App\Models\Department;
use App\Repositories\DepartmentRepository;

class StoreDepartmentService
{
    public function __construct(
        private readonly DepartmentRepository $departmentRepository,
    ) {
    }

    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function handle(array $data): Department
    {
        return $this->departmentRepository->create($data);
    }
}
