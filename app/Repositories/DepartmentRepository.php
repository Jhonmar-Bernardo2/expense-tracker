<?php

namespace App\Repositories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    /**
     * @return Collection<int, Department>
     */
    public function getForIndex(): Collection
    {
        return Department::query()
            ->withCount(['users', 'budgets', 'transactions'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Department>
     */
    public function getOptions(): Collection
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_financial_management', 'is_locked']);
    }

    public function findOrFail(int $departmentId): Department
    {
        return Department::query()->findOrFail($departmentId);
    }

    public function findFinancialManagement(): ?Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->first();
    }

    public function findFinancialManagementOrFail(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }

    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function create(array $data): Department
    {
        $payload = $this->normalizePayload($data);

        return Department::query()->create($payload);
    }

    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function update(Department $department, array $data): Department
    {
        $payload = $this->normalizePayload($data);

        $department->update($payload);

        return $department->refresh();
    }

    public function hasUsers(Department $department): bool
    {
        if ($department->isLocked()) {
            return true;
        }

        return $department->users()->exists()
            || $department->budgets()->exists()
            || $department->transactions()->exists();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }

    /**
     * @param  array{name: string, description: string|null}  $data
     * @return array{name: string, description: string|null}
     */
    private function normalizePayload(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'description' => $data['description'] === null ? null : trim($data['description']),
        ];
    }
}
