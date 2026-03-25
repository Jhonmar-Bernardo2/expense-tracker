<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download, Eye, MailCheck, Plus, Printer, Search, Send, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy as destroyApprovalMemo, index as approvalMemoIndex, show as approvalMemoShow, store as storeApprovalMemo } from '@/routes/approval-memos';
import type { ApprovalMemo, ApprovalMemoAction, ApprovalMemoModule, BreadcrumbItem, DepartmentOption, DepartmentScope, Paginator } from '@/types';

type FilterOption = { value: string; label: string };

const props = defineProps<{
    approval_memos: Paginator<ApprovalMemo>;
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    filters: { department: number | null; status: string | null; module: string | null; action: string | null; search: string | null };
    statuses: FilterOption[];
    modules: FilterOption[];
    actions: FilterOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Approval Memos', href: approvalMemoIndex() },
];

const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const selectedDepartment = ref<number | 'all'>(props.filters.department ?? 'all');
const selectedStatus = ref<string | 'all'>(props.filters.status ?? 'all');
const selectedModule = ref<string | 'all'>(props.filters.module ?? 'all');
const selectedAction = ref<string | 'all'>(props.filters.action ?? 'all');
const search = ref(props.filters.search ?? '');
const approvalMemoPendingDeletion = ref<ApprovalMemo | null>(null);
const deletingMemoId = ref<number | null>(null);

const canSelectDepartment = computed(() => props.department_scope.can_select_department);
const departmentLabel = computed(() => props.department_scope.is_all_departments ? 'All departments' : (props.department_scope.selected_department?.name ?? 'Assigned department'));
const pendingCount = computed(() => props.approval_memos.data.filter((memo) => memo.status === 'pending_approval').length);

const form = useForm({
    department_id: props.department_scope.department_id ?? props.departments[0]?.id ?? null,
    module: 'transaction' as ApprovalMemoModule,
    action: 'create' as ApprovalMemoAction,
    remarks: '',
});

const statusVariant = (status: ApprovalMemo['status']) => status === 'approved' ? 'default' : status === 'pending_approval' ? 'secondary' : status === 'rejected' ? 'destructive' : 'outline';
const formatDateTime = (value: string | null) => value ? new Date(value.replace(' ', 'T')).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '-';
const approvalMemoPendingDeletionLabel = computed(() => approvalMemoPendingDeletion.value?.memo_no ?? 'this approval memo');

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.department_id = props.department_scope.department_id ?? props.departments[0]?.id ?? null;
    form.module = 'transaction';
    form.action = 'create';
    form.remarks = '';

    if (canSelectDepartment.value && selectedDepartment.value !== 'all') {
        form.department_id = selectedDepartment.value;
    }
};

const applyFilters = () => {
    router.get(approvalMemoIndex.url({
        query: {
            department: selectedDepartment.value === 'all' ? undefined : selectedDepartment.value,
            status: selectedStatus.value === 'all' ? undefined : selectedStatus.value,
            module: selectedModule.value === 'all' ? undefined : selectedModule.value,
            action: selectedAction.value === 'all' ? undefined : selectedAction.value,
            search: search.value.trim() || undefined,
        },
    }), {}, { preserveScroll: true, preserveState: true, replace: true });
};

const handleDeleteDialogChange = (open: boolean) => {
    isDeleteDialogOpen.value = open;

    if (!open && deletingMemoId.value === null) {
        approvalMemoPendingDeletion.value = null;
    }
};

const openDeleteDialog = (approvalMemo: ApprovalMemo) => {
    approvalMemoPendingDeletion.value = approvalMemo;
    isDeleteDialogOpen.value = true;
};

const confirmDeleteMemo = () => {
    if (approvalMemoPendingDeletion.value === null) {
        return;
    }

    deletingMemoId.value = approvalMemoPendingDeletion.value.id;

    router.delete(destroyApprovalMemo(approvalMemoPendingDeletion.value.id).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isDeleteDialogOpen.value = false;
            approvalMemoPendingDeletion.value = null;
        },
        onFinish: () => {
            deletingMemoId.value = null;
        },
    });
};

const submit = (autoSubmit: boolean) => {
    form.transform((data) => ({ ...data, auto_submit: autoSubmit })).post(storeApprovalMemo().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            resetForm();
        },
    });
};
</script>

<template>
    <Head title="Approval Memos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl"><MailCheck class="size-5" />Approval Memos</CardTitle>
                            <CardDescription>Request a memo first, then link the approved memo to budget or transaction submissions.</CardDescription>
                        </div>
                        <Dialog v-model:open="isDialogOpen">
                            <DialogTrigger as-child>
                                <Button class="w-full sm:w-auto" @click="resetForm"><Plus class="mr-2 size-4" />Request approval memo</Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Request approval memo</DialogTitle>
                                    <DialogDescription>Save a draft or submit the memo request to admin for review.</DialogDescription>
                                </DialogHeader>
                                <form class="space-y-4" @submit.prevent="submit(false)">
                                    <div v-if="canSelectDepartment" class="grid gap-2">
                                        <Label>Department</Label>
                                        <Select v-model="form.department_id">
                                            <SelectTrigger><SelectValue placeholder="Select department" /></SelectTrigger>
                                            <SelectContent><SelectItem v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</SelectItem></SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.department_id" />
                                    </div>
                                    <div v-else class="rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"><span class="font-medium text-foreground">Department:</span> {{ departmentLabel }}</div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label>Module</Label>
                                            <Select v-model="form.module">
                                                <SelectTrigger><SelectValue placeholder="Select module" /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="module in modules" :key="module.value" :value="module.value">{{ module.label }}</SelectItem></SelectContent>
                                            </Select>
                                            <InputError :message="form.errors.module" />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Action</Label>
                                            <Select v-model="form.action">
                                                <SelectTrigger><SelectValue placeholder="Select action" /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="action in actions" :key="action.value" :value="action.value">{{ action.label }}</SelectItem></SelectContent>
                                            </Select>
                                            <InputError :message="form.errors.action" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Purpose or remarks</Label>
                                        <textarea v-model="form.remarks" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                                        <InputError :message="form.errors.remarks" />
                                    </div>
                                    <DialogFooter class="gap-2 sm:justify-end">
                                        <Button type="button" variant="secondary" @click="isDialogOpen = false">Cancel</Button>
                                        <Button type="button" variant="outline" :disabled="form.processing" @click="submit(false)"><Spinner v-if="form.processing" />Save draft</Button>
                                        <Button type="button" :disabled="form.processing" @click="submit(true)"><Spinner v-if="form.processing" /><Send class="mr-2 size-4" />Submit request</Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border bg-muted/30 p-4"><div class="text-sm text-muted-foreground">Total memos</div><div class="mt-2 text-2xl font-semibold">{{ approval_memos.meta.total }}</div></div>
                        <div class="rounded-lg border bg-muted/30 p-4"><div class="text-sm text-muted-foreground">Pending approval</div><div class="mt-2 text-2xl font-semibold">{{ pendingCount }}</div></div>
                        <div class="rounded-lg border bg-muted/30 p-4"><div class="text-sm text-muted-foreground">Current scope</div><div class="mt-2 text-sm font-medium">{{ departmentLabel }}</div></div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader><CardTitle>Filters</CardTitle><CardDescription>Filter by scope, status, module, action, or search.</CardDescription></CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="department_scope.can_select_department" class="grid gap-2"><Label>Department</Label><Select :model-value="selectedDepartment" @update:model-value="selectedDepartment = $event as number | 'all'; applyFilters()"><SelectTrigger><SelectValue placeholder="All departments" /></SelectTrigger><SelectContent><SelectItem value="all">All departments</SelectItem><SelectItem v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</SelectItem></SelectContent></Select></div>
                        <div class="grid gap-2"><Label>Status</Label><Select :model-value="selectedStatus" @update:model-value="selectedStatus = $event as string | 'all'; applyFilters()"><SelectTrigger><SelectValue placeholder="All statuses" /></SelectTrigger><SelectContent><SelectItem value="all">All statuses</SelectItem><SelectItem v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem></SelectContent></Select></div>
                        <div class="grid gap-2"><Label>Module</Label><Select :model-value="selectedModule" @update:model-value="selectedModule = $event as string | 'all'; applyFilters()"><SelectTrigger><SelectValue placeholder="All modules" /></SelectTrigger><SelectContent><SelectItem value="all">All modules</SelectItem><SelectItem v-for="module in modules" :key="module.value" :value="module.value">{{ module.label }}</SelectItem></SelectContent></Select></div>
                        <div class="grid gap-2"><Label>Action</Label><Select :model-value="selectedAction" @update:model-value="selectedAction = $event as string | 'all'; applyFilters()"><SelectTrigger><SelectValue placeholder="All actions" /></SelectTrigger><SelectContent><SelectItem value="all">All actions</SelectItem><SelectItem v-for="action in actions" :key="action.value" :value="action.value">{{ action.label }}</SelectItem></SelectContent></Select></div>
                        <div class="grid gap-2"><Label>Search</Label><div class="flex gap-2"><Input v-model="search" type="text" placeholder="Memo no., remarks, or requester" @keyup.enter="applyFilters" /><Button type="button" variant="outline" @click="applyFilters"><Search class="size-4" /></Button></div></div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Memo Queue</CardTitle><CardDescription>Track draft, pending, approved, and rejected memo requests.</CardDescription></CardHeader>
                <CardContent>
                    <div v-if="approval_memos.data.length === 0" class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No approval memos found for the current filters.</div>
                    <div v-else class="space-y-3">
                        <div v-for="approvalMemo in approval_memos.data" :key="approvalMemo.id" class="rounded-lg border p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ approvalMemo.memo_no }}</span><Badge :variant="statusVariant(approvalMemo.status)">{{ approvalMemo.status_label }}</Badge></div>
                                    <div class="text-sm text-muted-foreground">{{ approvalMemo.department?.name ?? '-' }} · {{ approvalMemo.module_label }} · {{ approvalMemo.action_label }}</div>
                                    <div class="text-sm text-muted-foreground">{{ approvalMemo.remarks || 'No remarks provided.' }}</div>
                                </div>
                                <div class="space-y-3 sm:text-right">
                                    <div class="space-y-1 text-sm text-muted-foreground">
                                        <div>Requester: {{ approvalMemo.requested_by_user?.name ?? '-' }}</div>
                                        <div>Updated: {{ formatDateTime(approvalMemo.updated_at) }}</div>
                                        <div>Linked voucher: {{ approvalMemo.linked_approval_voucher?.voucher_no ?? 'Not linked' }}</div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <span class="w-full text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase sm:text-right">Actions</span>
                                        <Button as-child variant="outline" size="sm">
                                            <Link :href="approvalMemoShow(approvalMemo.id)">
                                                <Eye class="mr-2 size-4" />
                                                View
                                            </Link>
                                        </Button>
                                        <Button v-if="approvalMemo.download_url" as-child variant="outline" size="sm">
                                            <a :href="approvalMemo.download_url">
                                                <Download class="mr-2 size-4" />
                                                Download PDF
                                            </a>
                                        </Button>
                                        <Button v-if="approvalMemo.print_url" as-child variant="outline" size="sm">
                                            <a :href="approvalMemo.print_url" target="_blank" rel="noopener noreferrer">
                                                <Printer class="mr-2 size-4" />
                                                Print
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="approvalMemo.permissions.can_delete"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="deletingMemoId === approvalMemo.id"
                                            @click="openDeleteDialog(approvalMemo)"
                                        >
                                            <Spinner v-if="deletingMemoId === approvalMemo.id" class="mr-2" />
                                            <Trash2 v-else class="mr-2 size-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                            <div>Page {{ approval_memos.meta.current_page }} of {{ approval_memos.meta.last_page }}</div>
                            <div class="flex gap-2">
                                <Button as-child variant="outline" size="sm" :class="!approval_memos.links.prev ? 'pointer-events-none opacity-50' : ''"><Link :href="approval_memos.links.prev ?? '#'">Previous</Link></Button>
                                <Button as-child variant="outline" size="sm" :class="!approval_memos.links.next ? 'pointer-events-none opacity-50' : ''"><Link :href="approval_memos.links.next ?? '#'">Next</Link></Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        <Dialog :open="isDeleteDialogOpen" @update:open="handleDeleteDialogChange">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete approval memo?</DialogTitle>
                    <DialogDescription>
                        {{ approvalMemoPendingDeletionLabel }} will be permanently removed. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:justify-end">
                    <Button type="button" variant="secondary" :disabled="deletingMemoId !== null" @click="handleDeleteDialogChange(false)">Cancel</Button>
                    <Button type="button" variant="destructive" :disabled="deletingMemoId !== null" @click="confirmDeleteMemo">
                        <Spinner v-if="deletingMemoId !== null" class="mr-2" />
                        Delete memo
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
