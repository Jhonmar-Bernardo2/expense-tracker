export type UserRole = 'admin' | 'staff';

export type DepartmentSummary = {
    id: number;
    name: string;
};

export type Department = {
    id: number;
    name: string;
    description: string | null;
    user_count: number;
    can_delete: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type DepartmentOption = DepartmentSummary;

export type DepartmentScope = {
    department_id: number | null;
    selected_department: DepartmentSummary | null;
    can_select_department: boolean;
    is_all_departments: boolean;
};

export type RoleOption = {
    value: UserRole;
    label: string;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    is_system_account: boolean;
    email_verified_at: string | null;
    department: DepartmentSummary | null;
    created_at: string | null;
    updated_at: string | null;
};
