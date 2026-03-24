import type { DepartmentSummary, UserRole } from './users';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: UserRole;
    is_active: boolean;
    is_system_account: boolean;
    department: DepartmentSummary | null;
    email_verified_at: string | null;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type Flash = {
    success?: string | null;
    error?: string | null;
    status?: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
