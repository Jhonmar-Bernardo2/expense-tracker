import type { ApprovalVoucher } from './approval-vouchers';
import type { Transaction } from './transactions';

export type DashboardViewMode = 'admin' | 'financial_management' | 'staff';

export type DashboardMetricFormat =
    | 'currency'
    | 'number'
    | 'percentage'
    | 'text';

export type DashboardMetricTone =
    | 'default'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info';

export type DashboardMetric = {
    id: string;
    label: string;
    value: number | string;
    format: DashboardMetricFormat;
    helper: string | null;
    tone: DashboardMetricTone;
};

export type DashboardAction = {
    id: string;
    label: string;
    href: string;
    variant: 'default' | 'outline' | 'secondary';
    icon: string | null;
};

export type DashboardAttentionBanner = {
    tone: 'default' | 'info' | 'warning' | 'danger';
    title: string;
    description: string;
    href: string | null;
    action_label: string | null;
};

export type DashboardApprovalSection = {
    id: string;
    title: string;
    description: string;
    empty_message: string;
    items: ApprovalVoucher[];
    cta_href?: string | null;
    cta_label?: string | null;
};

export type DashboardTransactionSection = {
    id: string;
    title: string;
    description: string;
    empty_message: string;
    items: Transaction[];
};

export type DashboardSection =
    | DashboardApprovalSection
    | DashboardTransactionSection;

export type DashboardView = {
    mode: DashboardViewMode;
    title: string;
    description: string;
    primary_metrics: DashboardMetric[];
    quick_actions: DashboardAction[];
    attention_banner?: DashboardAttentionBanner | null;
    primary_section: DashboardSection | null;
    secondary_section: DashboardSection | null;
};
