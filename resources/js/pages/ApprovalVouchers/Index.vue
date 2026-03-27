<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCheck, FileText, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DashboardMetricCard from '@/components/shared/DashboardMetricCard.vue';
import DashboardMetricGrid from '@/components/shared/DashboardMetricGrid.vue';
import ResponsiveActionGroup from '@/components/shared/ResponsiveActionGroup.vue';
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
import {
    displayApprovalModuleLabel,
    displayDepartmentName,
} from '@/lib/plain-language';
import { dashboard } from '@/routes';
import {
    index as approvalVoucherIndex,
    show as approvalVoucherShow,
} from '@/routes/approval-vouchers';
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
    { title: 'Requests', href: approvalVoucherIndex() },
];

const selectedDepartment = ref<number | 'all'>(
    props.filters.department ?? 'all',
);
const selectedStatus = ref<string | 'all'>(props.filters.status ?? 'all');
const selectedModule = ref<string | 'all'>(props.filters.module ?? 'all');
const selectedAction = ref<string | 'all'>(props.filters.action ?? 'all');
const search = ref(props.filters.search ?? '');
const summaryMetrics = computed(() => [
    {
        id: 'approval-total-requests',
        label: 'Total requests',
        value: props.approval_vouchers.meta.total.toLocaleString(),
        helper: 'All requests that match the current filters.',
        icon: FileText,
        tone: 'info' as const,
    },
    {
        id: 'approval-pending',
        label: 'Waiting for review',
        value: props.approval_vouchers.data
            .filter((item) => item.status === 'pending_approval')
            .length.toLocaleString(),
        helper: 'Requests waiting for review on this page.',
        icon: CheckCheck,
        tone: 'warning' as const,
    },
    {
        id: 'approval-scope',
        label: 'Current scope',
        value: props.department_scope.is_all_departments
            ? 'All departments'
            : displayDepartmentName(props.department_scope.selected_department),
        helper: 'What you are viewing right now.',
        icon: Search,
        tone: 'default' as const,
    },
]);

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
    <Head title="Requests" />

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
                                Requests
                            </CardTitle>
                            <CardDescription>
                                Review requests waiting for approval or already
                                processed.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <DashboardMetricGrid>
                            <DashboardMetricCard
                                v-for="metric in summaryMetrics"
                                :key="metric.id"
                                :label="metric.label"
                                :value="metric.value"
                                :helper="metric.helper"
                                :icon="metric.icon"
                                :tone="metric.tone"
                            />
                        </DashboardMetricGrid>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Find requests by department, type, status, or
                            keyword.
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
                                        {{
                                            displayDepartmentName(
                                                department,
                                                department.name,
                                            )
                                        }}
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
                                            {{
                                                displayApprovalModuleLabel(
                                                    module.value as
                                                        | 'transaction'
                                                        | 'budget'
                                                        | 'allocation',
                                                    module.label,
                                                )
                                            }}
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
                    <CardTitle>Request list</CardTitle>
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
                        No requests found for these filters.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="approvalVoucher in approval_vouchers.data"
                                :key="`approval-card-${approvalVoucher.id}`"
                                class="rounded-xl border p-4 shadow-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ approvalVoucher.subject }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ approvalVoucher.voucher_no }}
                                        </div>
                                    </div>
                                    <Badge
                                        :variant="
                                            statusVariant(
                                                approvalVoucher.status,
                                            )
                                        "
                                    >
                                        {{ approvalVoucher.status_label }}
                                    </Badge>
                                </div>

                                <div class="mt-4 grid gap-3">
                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {{
                                                displayApprovalModuleLabel(
                                                    approvalVoucher.module,
                                                    approvalVoucher.module_label,
                                                )
                                            }}
                                        </Badge>
                                        <Badge
                                            :variant="
                                                agingVariant(approvalVoucher)
                                            "
                                        >
                                            {{ agingLabel(approvalVoucher) }}
                                        </Badge>
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ approvalVoucher.action_label }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Department:
                                        {{
                                            approvalVoucher.department
                                                ? displayDepartmentName(
                                                      approvalVoucher.department,
                                                      approvalVoucher.department
                                                          .name,
                                                  )
                                                : 'Unassigned'
                                        }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Preparer:
                                        {{
                                            approvalVoucher.requested_by_user
                                                ?.name ?? '-'
                                        }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Date: {{ rowDate(approvalVoucher) }}
                                    </div>
                                </div>

                                <ResponsiveActionGroup class="mt-4" align="end">
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
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div
                            class="hidden overflow-hidden rounded-lg border md:block"
                        >
                            <Table>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Request no.</TableHead>
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
                                                {{
                                                    approvalVoucher.action_label
                                                }}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {{
                                                displayApprovalModuleLabel(
                                                    approvalVoucher.module,
                                                    approvalVoucher.module_label,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell
                                            v-if="
                                                department_scope.can_select_department
                                            "
                                        >
                                            {{
                                                approvalVoucher.department
                                                    ? displayDepartmentName(
                                                          approvalVoucher.department,
                                                          approvalVoucher
                                                              .department.name,
                                                      )
                                                    : '-'
                                            }}
                                        </TableCell>
                                        <TableCell>
                                            {{
                                                approvalVoucher
                                                    .requested_by_user?.name ??
                                                '-'
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
                                                {{
                                                    approvalVoucher.status_label
                                                }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="
                                                    agingVariant(
                                                        approvalVoucher,
                                                    )
                                                "
                                            >
                                                {{
                                                    agingLabel(approvalVoucher)
                                                }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{{
                                            rowDate(approvalVoucher)
                                        }}</TableCell>
                                        <TableCell>
                                            <ResponsiveActionGroup
                                                align="end"
                                                :full-width-on-mobile="false"
                                            >
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
                                            </ResponsiveActionGroup>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
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
