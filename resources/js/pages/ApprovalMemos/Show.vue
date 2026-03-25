<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    Printer,
    Send,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    approve as approveApprovalMemo,
    destroy as destroyApprovalMemo,
    index as approvalMemoIndex,
    reject as rejectApprovalMemo,
    submit as submitApprovalMemo,
    update as updateApprovalMemo,
} from '@/routes/approval-memos';
import { show as approvalVoucherShow } from '@/routes/approval-vouchers';
import type {
    ActivityLogItem,
    ApprovalMemo,
    ApprovalMemoAction,
    ApprovalMemoModule,
    BreadcrumbItem,
    DepartmentOption,
} from '@/types';

type FilterOption = { value: string; label: string };

const props = defineProps<{
    approval_memo: ApprovalMemo;
    activity_logs: ActivityLogItem[];
    departments: DepartmentOption[];
    modules: FilterOption[];
    actions: FilterOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Approval Memos', href: approvalMemoIndex() },
    { title: props.approval_memo.memo_no, href: approvalMemoIndex() },
];

const canEdit = computed(() => props.approval_memo.permissions.can_edit);
const canDelete = computed(() => props.approval_memo.permissions.can_delete);
const canDownload = computed(() => props.approval_memo.download_url !== null);
const canPrint = computed(
    () => props.approval_memo.permissions.can_print && props.approval_memo.print_url !== null,
);
const isDeleteDialogOpen = ref(false);
const isDeletingMemo = ref(false);

const form = useForm({
    department_id: props.approval_memo.department_id,
    module: props.approval_memo.module as ApprovalMemoModule,
    action: props.approval_memo.action as ApprovalMemoAction,
    remarks: props.approval_memo.remarks ?? '',
});

const approveForm = useForm({
    admin_remarks: props.approval_memo.admin_remarks ?? '',
});

const rejectForm = useForm({
    rejection_reason: props.approval_memo.rejection_reason ?? '',
});

const formatDateTime = (value: string | null) => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value.replace(' ', 'T'));

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const eventLabel = (event: string) =>
    event
        .split('.')
        .pop()
        ?.replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase()) ?? event;

const eventVariant = (event: string) => {
    if (event.endsWith('rejected')) {
        return 'destructive' as const;
    }

    if (event.includes('submitted') || event.includes('approved')) {
        return 'secondary' as const;
    }

    return 'outline' as const;
};

const saveDraft = () => {
    form.transform((data) => ({ ...data, _method: 'put' })).post(updateApprovalMemo(props.approval_memo.id).url, {
        preserveScroll: true,
        forceFormData: true,
    });
};

const submitRequest = () => {
    router.post(submitApprovalMemo(props.approval_memo.id).url, {}, { preserveScroll: true });
};

const approveRequest = () => {
    approveForm.transform((data) => ({ ...data, _method: 'patch' })).post(
        approveApprovalMemo(props.approval_memo.id).url,
        {
            preserveScroll: true,
            forceFormData: true,
        },
    );
};

const rejectRequest = () => {
    rejectForm.patch(rejectApprovalMemo(props.approval_memo.id).url, { preserveScroll: true });
};

const openPrintView = () => {
    if (!props.approval_memo.print_url) {
        return;
    }

    window.open(props.approval_memo.print_url, '_blank', 'noopener,noreferrer');
};

const openDownloadView = () => {
    if (!props.approval_memo.download_url) {
        return;
    }

    window.location.assign(props.approval_memo.download_url);
};

const confirmDeleteMemo = () => {
    isDeletingMemo.value = true;

    router.delete(destroyApprovalMemo(props.approval_memo.id).url, {
        onSuccess: () => {
            isDeleteDialogOpen.value = false;
        },
        onFinish: () => {
            isDeletingMemo.value = false;
        },
    });
};
</script>

<template>
    <Head :title="approval_memo.memo_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <Button as-child variant="ghost" size="sm" class="w-fit px-0">
                        <Link :href="approvalMemoIndex()"><ArrowLeft class="mr-2 size-4" />Back to queue</Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold">{{ approval_memo.memo_no }}</h1>
                        <p class="text-sm text-muted-foreground">{{ approval_memo.subject }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge>{{ approval_memo.status_label }}</Badge>
                        <Badge variant="outline">{{ approval_memo.module_label }}</Badge>
                        <Badge variant="outline">{{ approval_memo.action_label }}</Badge>
                    </div>
                </div>
                <div class="space-y-3 text-sm text-muted-foreground">
                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <Button v-if="canDelete" type="button" variant="outline" :disabled="isDeletingMemo" @click="isDeleteDialogOpen = true">
                            <Trash2 class="mr-2 size-4" />
                            Delete
                        </Button>
                        <Button v-if="canDownload" type="button" variant="outline" @click="openDownloadView">
                            <Download class="mr-2 size-4" />
                            Download PDF
                        </Button>
                        <Button v-if="canPrint" type="button" variant="outline" @click="openPrintView">
                            <Printer class="mr-2 size-4" />
                            Print
                        </Button>
                    </div>
                    <div><span class="font-medium text-foreground">Requester:</span> {{ approval_memo.requested_by_user?.name ?? '-' }}</div>
                    <div><span class="font-medium text-foreground">Department:</span> {{ approval_memo.department?.name ?? '-' }}</div>
                    <div><span class="font-medium text-foreground">Submitted:</span> {{ formatDateTime(approval_memo.submitted_at) }}</div>
                    <div><span class="font-medium text-foreground">Approved:</span> {{ formatDateTime(approval_memo.approved_at) }}</div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm xl:col-span-2">
                    <CardHeader>
                    <CardTitle>Memo Details</CardTitle>
                    <CardDescription>Review the request summary, remarks, and linkage details.</CardDescription>
                </CardHeader>
                    <CardContent class="space-y-4">
                        <Card class="bg-muted/30 shadow-none">
                            <CardHeader class="pb-3">
                                <CardDescription>Status</CardDescription>
                                <CardTitle class="text-2xl">{{ approval_memo.status_label }}</CardTitle>
                            </CardHeader>
                            <CardContent class="pt-0 text-sm text-muted-foreground">
                                Approved at {{ formatDateTime(approval_memo.approved_at) }}
                            </CardContent>
                        </Card>

                        <Card class="shadow-none">
                            <CardHeader class="pb-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle class="text-base">Request Details</CardTitle>
                                        <CardDescription>Core memo metadata and approval context.</CardDescription>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="outline">{{ approval_memo.module_label }}</Badge>
                                        <Badge variant="outline">{{ approval_memo.action_label }}</Badge>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="grid gap-3 md:grid-cols-2">
                                <Card class="shadow-none">
                                    <CardHeader class="pb-2">
                                        <CardDescription>Requester</CardDescription>
                                        <CardTitle class="text-sm">{{ approval_memo.requested_by_user?.name ?? '-' }}</CardTitle>
                                    </CardHeader>
                                    <CardContent class="pt-0 text-xs text-muted-foreground">
                                        {{ approval_memo.requested_by_user?.email ?? '-' }}
                                    </CardContent>
                                </Card>
                                <Card class="shadow-none">
                                    <CardHeader class="pb-2">
                                        <CardDescription>Department</CardDescription>
                                        <CardTitle class="text-sm">{{ approval_memo.department?.name ?? '-' }}</CardTitle>
                                    </CardHeader>
                                </Card>
                                <Card class="shadow-none">
                                    <CardHeader class="pb-2">
                                        <CardDescription>Approved By</CardDescription>
                                        <CardTitle class="text-sm">{{ approval_memo.approved_by_user?.name ?? '-' }}</CardTitle>
                                    </CardHeader>
                                    <CardContent class="pt-0 text-xs text-muted-foreground">
                                        {{ approval_memo.approved_by_user?.email ?? '-' }}
                                    </CardContent>
                                </Card>
                                <Card class="shadow-none">
                                    <CardHeader class="pb-2">
                                        <CardDescription>Submitted</CardDescription>
                                        <CardTitle class="text-sm">{{ formatDateTime(approval_memo.submitted_at) }}</CardTitle>
                                    </CardHeader>
                                </Card>
                            </CardContent>
                        </Card>

                        <Card class="shadow-none">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">Remarks</CardTitle>
                                <CardDescription>Requester and reviewer notes for this memo.</CardDescription>
                            </CardHeader>
                            <CardContent class="grid gap-3 md:grid-cols-2">
                                <Card class="shadow-none">
                                    <CardHeader class="pb-2">
                                        <CardDescription>Purpose or Remarks</CardDescription>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <p class="text-sm leading-7 whitespace-pre-line break-all text-foreground">{{ approval_memo.remarks ?? 'No remarks provided.' }}</p>
                                    </CardContent>
                                </Card>
                                <Card class="shadow-none">
                                    <CardHeader class="pb-2">
                                        <CardDescription>Admin Remarks</CardDescription>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <p class="text-sm leading-7 whitespace-pre-line break-all text-foreground">{{ approval_memo.admin_remarks ?? 'No admin remarks recorded.' }}</p>
                                    </CardContent>
                                </Card>
                            </CardContent>
                        </Card>

                        <Separator />

                        <div class="grid gap-3 md:grid-cols-2">
                            <Card class="shadow-none">
                                <CardHeader class="pb-2">
                                    <CardDescription>Rejection Reason</CardDescription>
                                </CardHeader>
                                <CardContent class="pt-0">
                                    <p class="text-sm leading-7 whitespace-pre-line break-all text-muted-foreground">{{ approval_memo.rejection_reason ?? 'No rejection recorded.' }}</p>
                                </CardContent>
                            </Card>
                            <Card class="shadow-none">
                                <CardHeader class="pb-2">
                                    <CardDescription>Linked Voucher</CardDescription>
                                </CardHeader>
                                <CardContent class="pt-0">
                                    <div class="text-sm text-muted-foreground">
                                        <Link
                                            v-if="approval_memo.linked_approval_voucher"
                                            :href="approvalVoucherShow(approval_memo.linked_approval_voucher.id)"
                                            class="font-semibold text-foreground hover:underline"
                                        >
                                            {{ approval_memo.linked_approval_voucher.voucher_no }}
                                        </Link>
                                        <span v-else>Not linked to an approval voucher yet.</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4 xl:col-start-2">
                    <Card v-if="canEdit" class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Update Request</CardTitle>
                            <CardDescription>Save your draft changes first, then submit the memo request for review.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="saveDraft">
                                <div class="grid gap-2">
                                    <Label>Department</Label>
                                    <Select v-model="form.department_id">
                                        <SelectTrigger><SelectValue placeholder="Select department" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="department in departments" :key="department.id" :value="department.id">
                                                {{ department.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.department_id" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>Module</Label>
                                        <Select v-model="form.module">
                                            <SelectTrigger><SelectValue placeholder="Select module" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="module in modules" :key="module.value" :value="module.value">
                                                    {{ module.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.module" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Action</Label>
                                        <Select v-model="form.action">
                                            <SelectTrigger><SelectValue placeholder="Select action" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="action in actions" :key="action.value" :value="action.value">
                                                    {{ action.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.action" />
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Purpose or remarks</Label>
                                    <textarea v-model="form.remarks" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                                    <InputError :message="form.errors.remarks" />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button type="submit" :disabled="form.processing"><Spinner v-if="form.processing" />Save draft</Button>
                                    <Button type="button" variant="outline" :disabled="form.processing || !approval_memo.permissions.can_submit" @click="submitRequest">
                                        <Send class="mr-2 size-4" />
                                        Submit request
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card v-if="approval_memo.permissions.can_approve || approval_memo.permissions.can_reject" class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Admin Review</CardTitle>
                            <CardDescription>Approving this request unlocks the memo print/export page for the requester.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div v-if="approval_memo.permissions.can_approve" class="space-y-3">
                                <div class="grid gap-2">
                                    <Label>Approval notes</Label>
                                    <textarea v-model="approveForm.admin_remarks" class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                                    <InputError :message="approveForm.errors.admin_remarks" />
                                </div>
                                <Button :disabled="approveForm.processing" @click="approveRequest"><Spinner v-if="approveForm.processing" />Approve memo</Button>
                            </div>
                            <div v-if="approval_memo.permissions.can_reject" class="space-y-3">
                                <div class="grid gap-2">
                                    <Label>Rejection reason</Label>
                                    <textarea v-model="rejectForm.rejection_reason" class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                                    <InputError :message="rejectForm.errors.rejection_reason" />
                                </div>
                                <Button variant="destructive" :disabled="rejectForm.processing" @click="rejectRequest"><Spinner v-if="rejectForm.processing" />Reject memo</Button>
                            </div>
                        </CardContent>
                    </Card>

                </div>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>History</CardTitle>
                    <CardDescription>Latest memo activity and review events.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="activity_logs.length === 0" class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                        No memo history recorded yet.
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="activityLog in activity_logs" :key="activityLog.id" class="rounded-lg border p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge :variant="eventVariant(activityLog.event)">{{ eventLabel(activityLog.event) }}</Badge>
                                        <span class="text-xs text-muted-foreground">{{ activityLog.actor?.name ?? 'System' }}</span>
                                    </div>
                                    <p class="text-sm font-medium text-foreground">{{ activityLog.summary }}</p>
                                </div>
                                <div class="text-xs text-muted-foreground">{{ formatDateTime(activityLog.created_at) }}</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete approval memo?</DialogTitle>
                    <DialogDescription>
                        {{ approval_memo.memo_no }} will be permanently removed. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:justify-end">
                    <Button type="button" variant="secondary" :disabled="isDeletingMemo" @click="isDeleteDialogOpen = false">Cancel</Button>
                    <Button type="button" variant="destructive" :disabled="isDeletingMemo" @click="confirmDeleteMemo">
                        <Spinner v-if="isDeletingMemo" class="mr-2" />
                        Delete memo
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
