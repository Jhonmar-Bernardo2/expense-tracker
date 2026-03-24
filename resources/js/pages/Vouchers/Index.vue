<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Eye, FileText, Pencil, Plus, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrev } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import * as voucherRoutes from '@/routes/vouchers';
import type { BreadcrumbItem, DepartmentOption, DepartmentScope, Paginator, Voucher, VoucherStatus, VoucherStatusOption, VoucherType, VoucherTypeOption } from '@/types';

const props = defineProps<{
    vouchers: Paginator<Voucher>;
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    filters: { department: number | null; status: VoucherStatus | null; type: VoucherType | null; search: string | null };
    statuses: VoucherStatusOption[];
    types: VoucherTypeOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Cash Vouchers', href: voucherRoutes.index() },
];

const isDialogOpen = ref(false);
const editingVoucher = ref<Voucher | null>(null);
const selectedDepartment = ref<number | 'all'>(props.filters.department ?? 'all');
const selectedStatus = ref<VoucherStatus | 'all'>(props.filters.status ?? 'all');
const selectedType = ref<VoucherType | 'all'>(props.filters.type ?? 'all');
const search = ref(props.filters.search ?? '');

const canSelectDepartment = computed(() => props.department_scope.can_select_department);
const departmentLabel = computed(() => props.department_scope.is_all_departments ? 'All departments' : (props.department_scope.selected_department?.name ?? 'Assigned department'));
const defaultType = computed<VoucherType>(() => props.types[0]?.value ?? 'cash_advance');

const form = useForm({
    department_id: props.department_scope.department_id ?? props.departments[0]?.id ?? null,
    type: defaultType.value,
    purpose: '',
    requested_amount: '',
    remarks: '',
});

const statusVariant = (status: VoucherStatus | null) => {
    switch (status) {
        case 'approved':
        case 'released':
        case 'liquidation_approved':
            return 'default';
        case 'rejected':
            return 'destructive';
        case 'pending_approval':
        case 'liquidation_submitted':
            return 'secondary';
        default:
            return 'outline';
    }
};

const amount = (value: string | null) => Number(value ?? 0).toFixed(2);

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.department_id = props.department_scope.department_id ?? props.departments[0]?.id ?? null;
    form.type = defaultType.value;

    if (canSelectDepartment.value && selectedDepartment.value !== 'all') {
        form.department_id = selectedDepartment.value;
    }
};

const openCreateDialog = () => {
    editingVoucher.value = null;
    resetForm();
    isDialogOpen.value = true;
};

const openEditDialog = (voucher: Voucher) => {
    editingVoucher.value = voucher;
    form.department_id = voucher.department_id;
    form.type = voucher.type ?? defaultType.value;
    form.purpose = voucher.purpose;
    form.requested_amount = voucher.requested_amount ?? '';
    form.remarks = voucher.remarks ?? '';
    form.clearErrors();
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    editingVoucher.value = null;
    resetForm();
};

const applyFilters = () => {
    router.get(voucherRoutes.index.url({
        query: {
            department: selectedDepartment.value === 'all' ? undefined : selectedDepartment.value,
            status: selectedStatus.value === 'all' ? undefined : selectedStatus.value,
            type: selectedType.value === 'all' ? undefined : selectedType.value,
            search: search.value.trim() || undefined,
        },
    }), {}, { preserveScroll: true, preserveState: true, replace: true });
};

const saveVoucher = () => {
    const action = editingVoucher.value ? voucherRoutes.update(editingVoucher.value.id) : voucherRoutes.store();
    form.submit(action.method, action.url, { preserveScroll: true, onSuccess: () => closeDialog() });
};

const submitVoucher = (voucher: Voucher) => {
    if (!window.confirm(`Submit voucher "${voucher.voucher_no}" for approval?`)) {
        return;
    }

    router.post(voucherRoutes.submit(voucher.id).url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Cash Vouchers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl"><FileText class="size-5" />Cash Vouchers</CardTitle>
                            <CardDescription>Request, approve, release, and liquidate cash advances or reimbursements.</CardDescription>
                        </div>

                        <Dialog v-model:open="isDialogOpen">
                            <DialogTrigger as-child><Button class="w-full sm:w-auto" @click="openCreateDialog"><Plus class="mr-2 size-4" />New cash voucher</Button></DialogTrigger>
                            <DialogContent class="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>{{ editingVoucher ? 'Edit voucher request' : 'Create voucher request' }}</DialogTitle>
                                    <DialogDescription>{{ editingVoucher ? 'Update the request before it moves forward again.' : 'Create a voucher request that will go through approval and liquidation.' }}</DialogDescription>
                                </DialogHeader>
                                <form class="space-y-5" @submit.prevent="saveVoucher">
                                    <div v-if="canSelectDepartment" class="grid gap-2">
                                        <Label for="voucher-department">Department</Label>
                                        <Select v-model="form.department_id">
                                            <SelectTrigger id="voucher-department" class="w-full"><SelectValue placeholder="Select a department" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.department_id" />
                                    </div>
                                    <div v-else class="rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"><span class="font-medium text-foreground">Department:</span> {{ departmentLabel }}</div>

                                    <div class="grid gap-2">
                                        <Label for="voucher-type">Type</Label>
                                        <Select v-model="form.type">
                                            <SelectTrigger id="voucher-type" class="w-full"><SelectValue placeholder="Select a type" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.type" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="voucher-purpose">Purpose</Label>
                                        <Input id="voucher-purpose" v-model="form.purpose" type="text" maxlength="255" required />
                                        <InputError :message="form.errors.purpose" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="voucher-amount">Requested amount</Label>
                                        <Input id="voucher-amount" v-model="form.requested_amount" type="number" min="0.01" step="0.01" required />
                                        <InputError :message="form.errors.requested_amount" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="voucher-remarks">Remarks</Label>
                                        <textarea id="voucher-remarks" v-model="form.remarks" rows="4" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" />
                                        <InputError :message="form.errors.remarks" />
                                    </div>

                                    <DialogFooter class="gap-2 sm:justify-end">
                                        <Button type="button" variant="secondary" @click="closeDialog">Cancel</Button>
                                        <Button type="submit" :disabled="form.processing"><Spinner v-if="form.processing" />{{ editingVoucher ? 'Save changes' : 'Create voucher' }}</Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader><CardTitle>Filters</CardTitle><CardDescription>Browse by department, status, type, or search.</CardDescription></CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="canSelectDepartment" class="grid gap-2">
                            <Label for="voucher-filter-department">Department</Label>
                            <Select :model-value="selectedDepartment" @update:model-value="selectedDepartment = $event as number | 'all'; applyFilters()">
                                <SelectTrigger id="voucher-filter-department" class="w-full"><SelectValue placeholder="All departments" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All departments</SelectItem>
                                    <SelectItem v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-else class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"><Building2 class="size-4" />{{ departmentLabel }}</div>

                        <div class="grid gap-2">
                            <Label for="voucher-filter-status">Status</Label>
                            <Select :model-value="selectedStatus" @update:model-value="selectedStatus = $event as VoucherStatus | 'all'; applyFilters()">
                                <SelectTrigger id="voucher-filter-status" class="w-full"><SelectValue placeholder="All statuses" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    <SelectItem v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="voucher-filter-type">Type</Label>
                            <Select :model-value="selectedType" @update:model-value="selectedType = $event as VoucherType | 'all'; applyFilters()">
                                <SelectTrigger id="voucher-filter-type" class="w-full"><SelectValue placeholder="All types" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All types</SelectItem>
                                    <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="voucher-filter-search">Search</Label>
                            <Input id="voucher-filter-search" v-model="search" type="text" @keyup.enter="applyFilters" />
                            <Button variant="outline" size="sm" @click="applyFilters">Apply</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Voucher queue</CardTitle><CardDescription>{{ vouchers.meta.total }} voucher{{ vouchers.meta.total === 1 ? '' : 's' }} found.</CardDescription></CardHeader>
                <CardContent>
                    <div v-if="vouchers.data.length === 0" class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No vouchers found for the current filters.</div>
                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead>Voucher</TableHead>
                                    <TableHead v-if="canSelectDepartment">Department</TableHead>
                                    <TableHead>Requester</TableHead>
                                    <TableHead>Purpose</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">Requested</TableHead>
                                    <TableHead class="text-right">Released</TableHead>
                                    <TableHead class="text-right">Liquidated</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="voucher in vouchers.data" :key="voucher.id">
                                    <TableCell><div class="font-medium">{{ voucher.voucher_no }}</div><div class="text-xs text-muted-foreground">{{ voucher.type_label }}</div></TableCell>
                                    <TableCell v-if="canSelectDepartment">{{ voucher.department?.name ?? '-' }}</TableCell>
                                    <TableCell>{{ voucher.requested_by?.name ?? '-' }}</TableCell>
                                    <TableCell><div class="font-medium">{{ voucher.purpose }}</div><div v-if="voucher.remarks" class="text-xs text-muted-foreground">{{ voucher.remarks }}</div></TableCell>
                                    <TableCell><Badge :variant="statusVariant(voucher.status)">{{ voucher.status_label }}</Badge></TableCell>
                                    <TableCell class="text-right tabular-nums">{{ amount(voucher.requested_amount) }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ amount(voucher.released_amount) }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ amount(voucher.liquidation_total) }}</TableCell>
                                    <TableCell>
                                        <div class="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" as-child><Link :href="voucherRoutes.show(voucher.id)"><Eye class="mr-2 size-4" />View</Link></Button>
                                            <Button variant="outline" size="sm" :disabled="!voucher.permissions.can_edit_request" @click="openEditDialog(voucher)"><Pencil class="mr-2 size-4" />Edit</Button>
                                            <Button variant="outline" size="sm" :disabled="!voucher.permissions.can_submit_request" @click="submitVoucher(voucher)"><Send class="mr-2 size-4" />Submit</Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div class="mt-4">
                        <Pagination v-if="vouchers.meta.last_page > 1">
                            <PaginationContent>
                                <PaginationItem><PaginationPrev :href="vouchers.links.prev" :disabled="!vouchers.links.prev" /></PaginationItem>
                                <PaginationItem v-for="link in vouchers.meta.links" :key="link.label">
                                    <PaginationLink v-if="link.label !== 'Previous' && link.label !== 'Next'" :href="link.url" :is-active="link.active" :disabled="!link.url"><span v-html="link.label" /></PaginationLink>
                                </PaginationItem>
                                <PaginationItem><PaginationNext :href="vouchers.links.next" :disabled="!vouchers.links.next" /></PaginationItem>
                            </PaginationContent>
                        </Pagination>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
