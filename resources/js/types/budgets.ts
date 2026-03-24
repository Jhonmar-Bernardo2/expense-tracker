import type { DepartmentSummary } from './users';

export type Budget = {
    id: number;
    department_id: number;
    category_id: number;
    category_name: string;
    month: number;
    year: number;
    amount_limit: number;
    amount_spent: number;
    amount_remaining: number;
    percentage_used: number;
    is_over_budget: boolean;
    department?: DepartmentSummary | null;
    created_at: string | null;
    updated_at: string | null;
};
