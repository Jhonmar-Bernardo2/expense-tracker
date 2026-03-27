import type { DepartmentSummary } from './users';

export type BudgetAccessShared = {
    can_view_page: boolean;
    can_manage_requests: boolean;
    can_manage_category_budgets: boolean;
    can_request_allocations: boolean;
    can_approve_transactions: boolean;
    can_approve_allocations: boolean;
    can_view_summaries: boolean;
    is_centralized: boolean;
    financial_management_department: DepartmentSummary;
};
