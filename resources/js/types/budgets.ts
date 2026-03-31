import type { DepartmentSummary } from './users';

export type BudgetPresetOption = {
    id: number;
    name: string;
    amount_limit: number;
};

export type BudgetPresetItem = {
    id: number;
    category_id: number;
    category_name: string | null;
    amount_limit: number;
    created_at?: string | null;
    updated_at?: string | null;
};

export type BudgetPreset = {
    id: number;
    category_id?: number | null;
    name: string;
    amount_limit?: number | null;
    items: BudgetPresetItem[];
    created_at?: string | null;
    updated_at?: string | null;
};

export type BudgetCategoryOption = {
    id: number;
    name: string;
    budget_presets: BudgetPresetOption[];
};

export type BudgetPresetCategoryOption = {
    id: number;
    name: string;
};

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
    total_approved_budget: number;
    total_budgeted: number;
    total_allocated: number;
    total_allocated_budget: number;
    total_unallocated: number;
    remaining_budget: number;
    total_spent: number;
    total_remaining: number;
    remaining_after_spending: number;
    categories_over_budget: number;
    can_allocate_category_budgets: boolean;
    allocation_block_message: string | null;
};
