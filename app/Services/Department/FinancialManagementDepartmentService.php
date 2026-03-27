<?php

namespace App\Services\Department;

use App\Models\Budget;
use App\Models\Department;
use App\Repositories\BudgetRepository;
use App\Repositories\DepartmentRepository;
use Illuminate\Database\Eloquent\Collection;

class FinancialManagementDepartmentService
{
    public const DEPARTMENT_NAME = 'Financial Management';

    public const DEPARTMENT_DESCRIPTION = 'Central budget department.';

    public function __construct(
        private readonly DepartmentRepository $departmentRepository,
        private readonly BudgetRepository $budgetRepository,
    ) {}

    public function getOrCreate(): Department
    {
        $department = $this->departmentRepository->findFinancialManagement();

        if ($department === null) {
            $department = $this->departmentRepository->findByNormalizedName(self::DEPARTMENT_NAME);
        }

        if ($department === null) {
            $department = $this->departmentRepository->createFinancialManagement(
                self::DEPARTMENT_NAME,
                self::DEPARTMENT_DESCRIPTION,
            );
        }

        $this->departmentRepository->clearFinancialManagementFlagsExcept($department->id);

        return $this->departmentRepository->lockAsFinancialManagement(
            $department,
            self::DEPARTMENT_NAME,
            self::DEPARTMENT_DESCRIPTION,
        );
    }

    public function getOrFail(): Department
    {
        return $this->departmentRepository->findFinancialManagementOrFail();
    }

    public function bootstrapCentralBudgetWorkflow(): Department
    {
        $department = $this->getOrCreate();

        $this->mergeActiveBudgetsIntoCentralDepartment($department);

        return $department;
    }

    public function mergeActiveBudgetsIntoCentralDepartment(Department $department): void
    {
        $groups = $this->budgetRepository->getActiveMergeGroups();

        $archivedAt = now();

        foreach ($groups as $group) {
            /** @var Collection<int, Budget> $budgets */
            $budgets = $this->budgetRepository->getActiveMergeCandidates(
                $department->id,
                (int) $group->category_id,
                (int) $group->month,
                (int) $group->year,
            );

            if ($budgets->isEmpty()) {
                continue;
            }

            $keeper = $budgets->first();
            $this->budgetRepository->reassignBudgetToDepartment(
                $keeper,
                $department->id,
                round($budgets->sum(fn (Budget $budget) => (float) $budget->amount_limit), 2),
            );

            $duplicateIds = $budgets
                ->slice(1)
                ->pluck('id')
                ->all();

            if ($duplicateIds === []) {
                continue;
            }

            $this->budgetRepository->archiveByIds($duplicateIds, $archivedAt);
        }
    }
}
