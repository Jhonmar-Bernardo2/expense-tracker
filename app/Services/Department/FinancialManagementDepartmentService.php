<?php

namespace App\Services\Department;

use App\Models\Budget;
use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class FinancialManagementDepartmentService
{
    public const DEPARTMENT_NAME = 'Financial Management';

    public const DEPARTMENT_DESCRIPTION = 'Central budget department.';

    public function getOrCreate(): Department
    {
        $department = Department::query()
            ->where('is_financial_management', true)
            ->orderBy('id')
            ->first();

        if ($department === null) {
            $department = Department::query()
                ->whereRaw('LOWER(name) = ?', [strtolower(self::DEPARTMENT_NAME)])
                ->orderBy('id')
                ->first();
        }

        if ($department === null) {
            $department = Department::query()->create([
                'name' => self::DEPARTMENT_NAME,
                'description' => self::DEPARTMENT_DESCRIPTION,
                'is_financial_management' => true,
                'is_locked' => true,
            ]);
        }

        Department::query()
            ->whereKeyNot($department->id)
            ->where('is_financial_management', true)
            ->update([
                'is_financial_management' => false,
                'is_locked' => false,
            ]);

        $department->forceFill([
            'name' => self::DEPARTMENT_NAME,
            'description' => $department->description ?: self::DEPARTMENT_DESCRIPTION,
            'is_financial_management' => true,
            'is_locked' => true,
        ])->save();

        return $department->refresh();
    }

    public function getOrFail(): Department
    {
        return Department::query()
            ->where('is_financial_management', true)
            ->firstOrFail();
    }

    public function bootstrapCentralBudgetWorkflow(): Department
    {
        $department = $this->getOrCreate();

        $this->mergeActiveBudgetsIntoCentralDepartment($department);

        return $department;
    }

    public function mergeActiveBudgetsIntoCentralDepartment(Department $department): void
    {
        $groups = Budget::query()
            ->active()
            ->select(['category_id', 'month', 'year'])
            ->groupBy('category_id', 'month', 'year')
            ->get();

        $archivedAt = now();

        foreach ($groups as $group) {
            /** @var Collection<int, Budget> $budgets */
            $budgets = Budget::query()
                ->active()
                ->where('category_id', $group->category_id)
                ->where('month', $group->month)
                ->where('year', $group->year)
                ->orderByRaw(
                    sprintf(
                        'CASE WHEN department_id = %d THEN 0 ELSE 1 END',
                        $department->id,
                    )
                )
                ->orderBy('id')
                ->get();

            if ($budgets->isEmpty()) {
                continue;
            }

            $keeper = $budgets->first();
            $keeper->update([
                'department_id' => $department->id,
                'amount_limit' => round($budgets->sum(fn (Budget $budget) => (float) $budget->amount_limit), 2),
            ]);

            $duplicateIds = $budgets
                ->slice(1)
                ->pluck('id')
                ->all();

            if ($duplicateIds === []) {
                continue;
            }

            Budget::query()
                ->whereKey($duplicateIds)
                ->update([
                    'archived_at' => $archivedAt,
                    'archived_by_approval_voucher_id' => null,
                    'updated_at' => $archivedAt,
                ]);
        }
    }
}
