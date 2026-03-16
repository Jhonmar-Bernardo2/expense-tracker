import type { CategoryType } from './categories';

export type TransactionType = CategoryType;

export type TransactionCategory = {
    id: number;
    name: string;
    type: TransactionType;
};

export type Transaction = {
    id: number;
    user_id: number;
    category_id: number;
    type: TransactionType;
    title: string;
    amount: string;
    description: string | null;
    transaction_date: string | null;
    category?: TransactionCategory;
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