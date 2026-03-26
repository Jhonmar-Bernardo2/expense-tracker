import type { CategoryType } from './categories';
import type { DepartmentSummary } from './users';

export type ApprovalVoucherModule = 'transaction' | 'budget';

export type ApprovalVoucherAction = 'create' | 'update' | 'delete';

export type ApprovalVoucherStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'rejected';

export type ApprovalVoucherPermission = {
    can_edit: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
};

export type ApprovalVoucherAttachment = {
    id: number;
    kind: 'supporting_document';
    kind_label: string;
    name: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string | null;
    download_url: string;
};

export type TransactionApprovalVoucherPayload = {
    department_id: number;
    category_id: number;
    type: CategoryType;
    title: string;
    amount: number;
    description: string | null;
    transaction_date: string | null;
};

export type BudgetApprovalVoucherPayload = {
    department_id: number;
    category_id: number;
    month: number;
    year: number;
    amount_limit: number;
};

export type ApprovalVoucherPayload =
    | TransactionApprovalVoucherPayload
    | BudgetApprovalVoucherPayload
    | null;

export type ApprovalVoucherUserSummary = {
    id: number;
    name: string;
    email: string;
};

export type ApprovalVoucher = {
    id: number;
    voucher_no: string;
    department_id: number;
    requested_by: number;
    approved_by: number | null;
    module: ApprovalVoucherModule;
    module_label: string;
    action: ApprovalVoucherAction;
    action_label: string;
    status: ApprovalVoucherStatus;
    status_label: string;
    pending_age_days: number | null;
    is_overdue: boolean;
    target_id: number | null;
    subject: string;
    before_payload: ApprovalVoucherPayload;
    after_payload: ApprovalVoucherPayload;
    remarks: string | null;
    rejection_reason: string | null;
    permissions: ApprovalVoucherPermission;
    attachments: ApprovalVoucherAttachment[];
    department?: DepartmentSummary | null;
    requested_by_user?: ApprovalVoucherUserSummary | null;
    approved_by_user?: ApprovalVoucherUserSummary | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    applied_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};
