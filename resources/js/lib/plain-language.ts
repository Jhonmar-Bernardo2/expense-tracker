import type { ApprovalVoucherModule, DepartmentSummary } from '@/types';

type DepartmentLike =
    | Pick<DepartmentSummary, 'name' | 'is_financial_management'>
    | null
    | undefined;

export const FINANCE_TEAM_LABEL = 'Finance Team';
export const MONTHLY_BUDGET_LABEL = 'Monthly budget';

export const displayDepartmentName = (
    department: DepartmentLike,
    fallback = 'Assigned department',
) => {
    if (department === null || department === undefined) {
        return fallback;
    }

    return department.is_financial_management
        ? FINANCE_TEAM_LABEL
        : department.name;
};

export const displayApprovalModuleLabel = (
    module: ApprovalVoucherModule,
    fallback?: string,
) => {
    if (module === 'allocation') {
        return MONTHLY_BUDGET_LABEL;
    }

    return fallback ?? (module === 'transaction' ? 'Transaction' : 'Budget');
};
