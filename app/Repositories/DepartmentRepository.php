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

    public function findByNormalizedName(string $name): ?Department
    {
        return Department::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->orderBy('id')
            ->first();
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

    public function createFinancialManagement(string $name, string $description): Department
    {
        return Department::query()->create([
            'name' => $name,
            'description' => $description,
            'is_financial_management' => true,
            'is_locked' => true,
        ]);
    }

    public function clearFinancialManagementFlagsExcept(int $departmentId): void
    {
        Department::query()
            ->whereKeyNot($departmentId)
            ->where('is_financial_management', true)
            ->update([
                'is_financial_management' => false,
                'is_locked' => false,
            ]);
    }

    public function lockAsFinancialManagement(
        Department $department,
        string $name,
        string $description,
    ): Department {
        $department->forceFill([
            'name' => $name,
            'description' => $department->description ?: $description,
            'is_financial_management' => true,
            'is_locked' => true,
        ])->save();

        return $department->refresh();
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
