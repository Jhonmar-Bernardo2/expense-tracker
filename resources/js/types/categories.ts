export type CategoryType = 'income' | 'expense';

export type CategoryBudgetPreset = {
    id: number;
    category_id: number | null;
    name: string;
    amount_limit: number | null;
    items?: Array<{
        id: number;
        category_id: number;
        category_name?: string | null;
        amount_limit: number;
    }>;
    created_at: string | null;
    updated_at: string | null;
};

export type Category = {
    id: number;
    name: string;
    type: CategoryType;
    transaction_count: number;
    budget_count: number;
    budget_presets: CategoryBudgetPreset[];
    has_budget_preset: boolean;
    budget_preset_count: number;
    can_delete: boolean;
    created_at: string | null;
    updated_at: string | null;
};
