import type { CategoryType } from './categories';
import type { DepartmentSummary } from './users';

export type TransactionType = CategoryType;

export type TransactionCategory = {
    id: number;
    name: string;
    type: TransactionType;
};

export type Transaction = {
    id: number;
    user_id: number;
    department_id: number;
    voucher_id: number | null;
    origin_approval_voucher_id: number | null;
    voided_by_approval_voucher_id: number | null;
    category_id: number;
    type: TransactionType;
    title: string;
    amount: string;
    description: string | null;
    transaction_date: string | null;
    voided_at: string | null;
    is_voided: boolean;
    category?: TransactionCategory;
    department?: DepartmentSummary | null;
    created_at: string | null;
    updated_at: string | null;
};

export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatorMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginatorLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

export type PaginatorLinks = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type Paginator<T> = {
    data: T[];
    links: PaginatorLinks;
    meta: PaginatorMeta;
};
