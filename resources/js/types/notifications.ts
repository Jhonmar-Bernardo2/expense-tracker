export type NotificationItem = {
    id: string;
    title: string;
    body: string;
    href: string | null;
    meta: Record<string, unknown>;
    is_read: boolean;
    created_at: string | null;
};

export type NotificationsShared = {
    unread_count: number;
};

export type WorkflowShared = {
    pending_approval_vouchers_count: number;
    pending_approval_memos_count: number;
};
