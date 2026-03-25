<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCheck,
    FileText,
    Plus,
    Receipt,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    index as approvalVoucherIndex,
    show as approvalVoucherShow,
} from '@/routes/approval-vouchers';
import { index as budgets } from '@/routes/budgets';
import { index as transactions } from '@/routes/transactions';
import type {
    ApprovalVoucher,
    BreadcrumbItem,
    DepartmentOption,
    DepartmentScope,
    Paginator,
} from '@/types';

type FilterOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    approval_vouchers: Paginator<ApprovalVoucher>;
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    filters: {
        department: number | null;
        status: string | null;
        module: string | null;
        action: string | null;
        search: string | null;
    };
    statuses: FilterOption[];
    modules: FilterOption[];
    actions: FilterOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Approval Vouchers', href: approvalVoucherIndex() },
];

const selectedDepartment = ref<number | 'all'>(
    props.filters.department ?? 'all',
);
const selectedStatus = ref<string | 'all'>(props.filters.status ?? 'all');
const selectedModule = ref<string | 'all'>(props.filters.module ?? 'all');
const selectedAction = ref<string | 'all'>(props.filters.action ?? 'all');
const search = ref(props.filters.search ?? '');

const applyFilters = () => {
    router.get(
        approvalVoucherIndex.url({
            query: {
                department:
                    selectedDepartment.value === 'all'
                        ? undefined
                        : selectedDepartment.value,
                status:
                    selectedStatus.value === 'all'
                        ? undefined
                        : selectedStatus.value,
                module:
                    selectedModule.value === 'all'
                        ? undefined
                        : selectedModule.value,
                action:
                    selectedAction.value === 'all'
                        ? undefined
                        : selectedAction.value,
                search: search.value.trim() || undefined,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const statusVariant = (status: ApprovalVoucher['status']) => {
    if (status === 'approved') {
        return 'default' as const;
    }

    if (status === 'pending_approval') {
        return 'secondary' as const;
    }

    if (status === 'rejected') {
        return 'destructive' as const;
    }

    return 'outline' as const;
};

const rowDate = (approvalVoucher: ApprovalVoucher) =>
    approvalVoucher.applied_at ??
    approvalVoucher.approved_at ??
    approvalVoucher.submitted_at ??
    approvalVoucher.created_at ??
    '-';
const agingLabel = (approvalVoucher: ApprovalVoucher) => {
    if (approvalVoucher.pending_age_days === null) {
        return '-';
    }

    return approvalVoucher.is_overdue
        ? `${approvalVoucher.pending_age_days}d overdue`
        : `${approvalVoucher.pending_age_days}d pending`;
};
const agingVariant = (approvalVoucher: ApprovalVoucher) => {
    if (approvalVoucher.pending_age_days === null) {
        return 'outline' as const;
    }

    return approvalVoucher.is_overdue
        ? ('destructive' as const)
        : ('secondary' as const);
};
</script>

<template>
    <Head title="Approval Vouchers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <ShieldCheck class="size-5" />
                                Approval Vouchers
                            </CardTitle>
                            <CardDescription>
                                Transaction and budget changes only affect final
                                records after admin approval.
                            </CardDescription>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Button as-child variant="outline">
                                <Link :href="transactions()">
                                    <Receipt class="mr-2 size-4" />
                                    Request transaction
                                </Link>
                            </Button>
                            <Button as-child>
                                <Link :href="budgets()">
                                    <Plus class="mr-2 size-4" />
                                    Request budget
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border bg-muted/30 p-4">
                            <div
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <FileText class="size-4" />
                                Total requests
                            </div>
                            <div class="mt-2 text-2xl font-semibold">
                                {{ approval_vouchers.meta.total }}
                            </div>
                        </div>
                        <div class="rounded-lg border bg-muted/30 p-4">
                            <div
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <CheckCheck class="size-4" />
                                Pending approval
                            </div>
                            <div class="mt-2 text-2xl font-semibold">
                                {{
                                    approval_vouchers.data.filter(
                                        (item) =>
                                            item.status === 'pending_approval',
                                    ).length
                                }}
                            </div>
                        </div>
                        <div class="rounded-lg border bg-muted/30 p-4">
                            <div
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Search class="size-4" />
                                Current scope
                            </div>
                            <div class="mt-2 text-sm font-medium">
                                {{
                                    department_scope.is_all_departments
                                        ? 'All departments'
                                        : (department_scope.selected_department
                                              ?.name ?? 'Assigned department')
                                }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Narrow the approval queue by scope, type, status,
                            and search.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-if="department_scope.can_select_department"
                            class="grid gap-2"
                        >
                            <Label for="filter-approval-department"
                                >Department</Label
                            >
                            <Select
                                :model-value="selectedDepartment"
                                @update:model-value="
                                    selectedDepartment = $event as
                                        | number
                                        | 'all';
                                    applyFilters();
                                "
                            >
                                <SelectTrigger id="filter-approval-department">
                                    <SelectValue
                                        placeholder="All departments"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All departments</SelectItem
                                    >
                                    <SelectItem
                                        v-for="department in departments"
                                        :key="department.id"
                                        :value="department.id"
                                    >
                                        {{ department.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="filter-approval-status">Status</Label>
                            <Select
                                :model-value="selectedStatus"
                                @update:model-value="
                                    selectedStatus = $event as string | 'all';
                                    applyFilters();
                                "
                            >
                                <SelectTrigger id="filter-approval-status">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All statuses</SelectItem
                                    >
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status.value"
                                        :value="status.value"
                                    >
                                        {{ status.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="filter-approval-module"
                                    >Module</Label
                                >
                                <Select
                                    :model-value="selectedModule"
                                    @update:model-value="
                                        selectedModule = $event as
                                            | string
                                            | 'all';
                                        applyFilters();
                                    "
                                >
                                    <SelectTrigger id="filter-approval-module">
                                        <SelectValue
                                            placeholder="All modules"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all"
                                            >All modules</SelectItem
                                        >
                                        <SelectItem
                                            v-for="module in modules"
                                            :key="module.value"
                                            :value="module.value"
                                        >
                                            {{ module.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="filter-approval-action"
                                    >Action</Label
                                >
                                <Select
                                    :model-value="selectedAction"
                                    @update:model-value="
                                        selectedAction = $event as
                                            | string
                                            | 'all';
                                        applyFilters();
                                    "
                                >
                                    <SelectTrigger id="filter-approval-action">
                                        <SelectValue
                                            placeholder="All actions"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all"
                                            >All actions</SelectItem
                                        >
                                        <SelectItem
                                            v-for="action in actions"
                                            :key="action.value"
                                            :value="action.value"
                                        >
                                            {{ action.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="filter-approval-search">Search</Label>
                            <Input
                                id="filter-approval-search"
                                v-model="search"
                                type="text"
                                @keyup.enter="applyFilters"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                @click="applyFilters"
                            >
                                Apply
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Queue</CardTitle>
                    <CardDescription>
                        {{ approval_vouchers.meta.total }} request{{
                            approval_vouchers.meta.total === 1 ? '' : 's'
                        }}
                        matched the current filters.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="approval_vouchers.data.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No approval vouchers found for the current filters.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead>Voucher</TableHead>
                                    <TableHead>Subject</TableHead>
                                    <TableHead>Module</TableHead>
                                    <TableHead
                                        v-if="
                                            department_scope.can_select_department
                                        "
                                    >
                                        Department
                                    </TableHead>
                                    <TableHead>Preparer</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Aging</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead class="text-right"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="approvalVoucher in approval_vouchers.data"
                                    :key="approvalVoucher.id"
                                >
                                    <TableCell class="font-medium">
                                        {{ approvalVoucher.voucher_no }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ approvalVoucher.subject }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ approvalVoucher.action_label }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{ approvalVoucher.module_label }}
                                    </TableCell>
                                    <TableCell
                                        v-if="
                                            department_scope.can_select_department
                                        "
                                    >
                                        {{
                                            approvalVoucher.department?.name ??
                                            '-'
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            approvalVoucher.requested_by_user
                                                ?.name ?? '-'
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                statusVariant(
                                                    approvalVoucher.status,
                                                )
                                            "
                                        >
                                            {{ approvalVoucher.status_label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                agingVariant(approvalVoucher)
                                            "
                                        >
                                            {{ agingLabel(approvalVoucher) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{
                                        rowDate(approvalVoucher)
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="flex justify-end">
                                            <Button
                                                as-child
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    :href="
                                                        approvalVoucherShow(
                                                            approvalVoucher.id,
                                                        )
                                                    "
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div class="mt-4">
                        <Pagination v-if="approval_vouchers.meta.last_page > 1">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrev
                                        :href="approval_vouchers.links.prev"
                                        :disabled="
                                            !approval_vouchers.links.prev
                                        "
                                    />
                                </PaginationItem>
                                <PaginationItem
                                    v-for="link in approval_vouchers.meta.links"
                                    :key="link.label"
                                >
                                    <PaginationLink
                                        v-if="
                                            link.label !== 'Previous' &&
                                            link.label !== 'Next'
                                        "
                                        :href="link.url"
                                        :is-active="link.active"
                                        :disabled="!link.url"
                                    >
                                        <span v-html="link.label" />
                                    </PaginationLink>
                                </PaginationItem>
                                <PaginationItem>
                                    <PaginationNext
                                        :href="approval_vouchers.links.next"
                                        :disabled="
                                            !approval_vouchers.links.next
                                        "
                                    />
                                </PaginationItem>
                            </PaginationContent>
                        </Pagination>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
