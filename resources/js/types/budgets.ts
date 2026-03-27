import type { DepartmentSummary } from './users';

export type Budget = {
    id: number;
    department_id: number;
    origin_approval_voucher_id: number | null;
    archived_by_approval_voucher_id: number | null;
    category_id: number;
    category_name: string;
    month: number;
    year: number;
    amount_limit: number;
    amount_spent: number;
    amount_remaining: number;
    percentage_used: number;
    is_over_budget: boolean;
    archived_at: string | null;
    is_archived: boolean;
    department?: DepartmentSummary | null;
    created_at: string | null;
    updated_at: string | null;
};

export type BudgetAllocation = {
    id: number;
    department_id: number;
    origin_approval_voucher_id: number | null;
    archived_by_approval_voucher_id: number | null;
    month: number;
    year: number;
    amount_limit: number;
    approved_amount: number;
    total_allocated: number;
    amount_remaining: number;
    is_over_allocated: boolean;
    archived_at: string | null;
    is_archived: boolean;
    department?: DepartmentSummary | null;
    created_at: string | null;
    updated_at: string | null;
};

export type BudgetAllocationSummary = {
    approved_allocation: number;
    total_budgeted: number;
    total_allocated: number;
    total_unallocated: number;
    total_spent: number;
    total_remaining: number;
    categories_over_budget: number;
};
