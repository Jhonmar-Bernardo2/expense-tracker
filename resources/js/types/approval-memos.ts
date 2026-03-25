import type { DepartmentSummary } from './users';

export type ApprovalMemoModule = 'transaction' | 'budget';

export type ApprovalMemoAction = 'create' | 'update';

export type ApprovalMemoStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'rejected';

export type ApprovalMemoPermission = {
    can_edit: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_delete: boolean;
    can_print: boolean;
};

export type ApprovalMemoUserSummary = {
    id: number;
    name: string;
    email: string;
};

export type ApprovalMemoLinkedVoucher = {
    id: number;
    voucher_no: string;
    status: 'draft' | 'pending_approval' | 'approved' | 'rejected';
    status_label: string;
};

export type ApprovalMemoSummary = {
    id: number;
    memo_no: string;
    department_id: number;
    module: ApprovalMemoModule;
    module_label: string;
    action: ApprovalMemoAction;
    action_label: string;
    status: ApprovalMemoStatus;
    status_label: string;
    remarks: string | null;
    approved_at: string | null;
    download_url: string | null;
    print_url: string | null;
    department?: DepartmentSummary | null;
};

export type ApprovalMemoOption = {
    id: number;
    memo_no: string;
    department_id: number;
    department_name: string | null;
    module: ApprovalMemoModule;
    module_label: string;
    action: ApprovalMemoAction;
    action_label: string;
    remarks: string | null;
    approved_at: string | null;
    download_url: string;
    print_url: string;
};

export type ApprovalMemo = {
    id: number;
    memo_no: string;
    department_id: number;
    requested_by: number;
    approved_by: number | null;
    module: ApprovalMemoModule;
    module_label: string;
    action: ApprovalMemoAction;
    action_label: string;
    status: ApprovalMemoStatus;
    status_label: string;
    subject: string;
    remarks: string | null;
    admin_remarks: string | null;
    rejection_reason: string | null;
    permissions: ApprovalMemoPermission;
    download_url: string | null;
    print_url: string | null;
    linked_approval_voucher?: ApprovalMemoLinkedVoucher | null;
    department?: DepartmentSummary | null;
    requested_by_user?: ApprovalMemoUserSummary | null;
    approved_by_user?: ApprovalMemoUserSummary | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};
