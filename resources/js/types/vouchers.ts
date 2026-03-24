import type { CategoryType } from './categories';
import type { DepartmentSummary } from './users';

export type VoucherStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'rejected'
    | 'released'
    | 'liquidation_submitted'
    | 'liquidation_returned'
    | 'liquidation_approved';

export type VoucherType = 'cash_advance' | 'reimbursement';

export type VoucherActor = {
    id: number;
    name: string;
    email: string;
};

export type VoucherCategory = {
    id: number;
    name: string;
    type: CategoryType;
};

export type VoucherPermission = {
    can_edit_request: boolean;
    can_submit_request: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_release: boolean;
    can_submit_liquidation: boolean;
    can_return_liquidation: boolean;
    can_approve_liquidation: boolean;
};

export type VoucherItem = {
    id: number;
    category_id: number;
    description: string;
    amount: string | null;
    expense_date: string | null;
    category: VoucherCategory | null;
};

export type VoucherAttachment = {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number;
    download_url: string;
    uploaded_by: VoucherActor | null;
    created_at: string | null;
};

export type VoucherLog = {
    id: number;
    action: string;
    action_label: string;
    from_status: VoucherStatus | null;
    from_status_label: string | null;
    to_status: VoucherStatus | null;
    to_status_label: string | null;
    remarks: string | null;
    user: VoucherActor | null;
    created_at: string | null;
};

export type VoucherTransaction = {
    id: number;
    title: string;
    amount: string;
    transaction_date: string | null;
    category: VoucherCategory | null;
};

export type Voucher = {
    id: number;
    voucher_no: string;
    department_id: number;
    requested_by_user_id: number;
    type: VoucherType | null;
    type_label: string | null;
    status: VoucherStatus | null;
    status_label: string | null;
    purpose: string;
    remarks: string | null;
    rejection_reason: string | null;
    liquidation_return_reason: string | null;
    requested_amount: string | null;
    approved_amount: string | null;
    released_amount: string | null;
    liquidation_total: string | null;
    liquidation_due_date: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    released_at: string | null;
    liquidation_submitted_at: string | null;
    liquidation_reviewed_at: string | null;
    posted_at: string | null;
    attachments_count: number;
    department?: DepartmentSummary | null;
    requested_by?: VoucherActor | null;
    approved_by?: VoucherActor | null;
    released_by?: VoucherActor | null;
    liquidation_reviewed_by?: VoucherActor | null;
    items?: VoucherItem[];
    attachments?: VoucherAttachment[];
    logs?: VoucherLog[];
    transactions?: VoucherTransaction[];
    permissions: VoucherPermission;
    created_at: string | null;
    updated_at: string | null;
};

export type VoucherStatusOption = {
    value: VoucherStatus;
    label: string;
};

export type VoucherTypeOption = {
    value: VoucherType;
    label: string;
};
