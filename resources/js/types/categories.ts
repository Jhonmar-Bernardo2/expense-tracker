export type CategoryType = 'income' | 'expense';

export type Category = {
    id: number;
    name: string;
    type: CategoryType;
    transaction_count: number;
    budget_count: number;
    can_delete: boolean;
    created_at: string | null;
    updated_at: string | null;
};
