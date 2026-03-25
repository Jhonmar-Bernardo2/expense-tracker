export type ActivityLogActor = {
    id: number;
    name: string;
    email: string;
} | null;

export type ActivityLogItem = {
    id: number;
    event: string;
    summary: string;
    meta: Record<string, unknown>;
    actor: ActivityLogActor;
    created_at: string | null;
};
