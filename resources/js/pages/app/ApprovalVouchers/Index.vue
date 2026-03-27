<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCheck,
    Download,
    Eye,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ResponsiveActionGroup from '@/components/shared/ResponsiveActionGroup.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { openPrintDialog } from '@/lib/print';
import {
    displayApprovalModuleLabel,
    displayDepartmentName,
} from '@/lib/plain-language';
import { dashboard } from '@/routes/app';
import {
    download as downloadVoucher,
    index as approvalVoucherIndex,
    print as printVoucher,
    show as showVoucher,
} from '@/routes/app/approval-vouchers';
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

const canSelectDepartment = computed(
    () => props.department_scope.can_select_department,
);
const normalizedSearch = computed(() => search.value.trim());
const pendingReviewCount = computed(
    () =>
        props.approval_vouchers.data.filter(
            (item) => item.status === 'pending_approval',
        ).length,
);
const currentScopeLabel = computed(() =>
    props.department_scope.is_all_departments
        ? 'All departments'
        : displayDepartmentName(props.department_scope.selected_department),
);
const appliedDepartmentFilterLabel = computed(() => {
    if (!canSelectDepartment.value || props.filters.department === null) {
        return null;
    }

    const department = props.departments.find(
        (option) => option.id === props.filters.department,
    );

    return department
        ? displayDepartmentName(department, department.name)
        : null;
});
const appliedStatusFilterLabel = computed(() =>
    props.filters.status === null
        ? null
        : (props.statuses.find((status) => status.value === props.filters.status)
              ?.label ?? null),
);
const appliedModuleFilterLabel = computed(() => {
    if (props.filters.module === null) {
        return null;
    }

    const module = props.modules.find(
        (option) => option.value === props.filters.module,
    );

    return module
        ? displayApprovalModuleLabel(
              module.value as 'transaction' | 'budget' | 'allocation',
              module.label,
          )
        : null;
});
const appliedActionFilterLabel = computed(() =>
    props.filters.action === null
        ? null
        : (props.actions.find((action) => action.value === props.filters.action)
              ?.label ?? null),
);
const activeFilterBadges = computed(() => {
    const badges: string[] = [];

    if (appliedDepartmentFilterLabel.value) {
        badges.push(`Department: ${appliedDepartmentFilterLabel.value}`);
    }

    if (appliedStatusFilterLabel.value) {
        badges.push(`Status: ${appliedStatusFilterLabel.value}`);
    }

    if (appliedModuleFilterLabel.value) {
        badges.push(`Module: ${appliedModuleFilterLabel.value}`);
    }

    if (appliedActionFilterLabel.value) {
        badges.push(`Action: ${appliedActionFilterLabel.value}`);
    }

    if ((props.filters.search ?? '').trim()) {
        badges.push(`Search: ${(props.filters.search ?? '').trim()}`);
    }

    return badges;
});
const hasActiveFilters = computed(() => activeFilterBadges.value.length > 0);
const filtersAreDirty = computed(
    () =>
        selectedDepartment.value !== (props.filters.department ?? 'all') ||
        selectedStatus.value !== (props.filters.status ?? 'all') ||
        selectedModule.value !== (props.filters.module ?? 'all') ||
        selectedAction.value !== (props.filters.action ?? 'all') ||
        normalizedSearch.value !== (props.filters.search ?? '').trim(),
);
const resultsSummary = computed(() => {
    const { from, to, total } = props.approval_vouchers.meta;
    const formattedTotal = total.toLocaleString();

    if (from !== null && to !== null) {
        return `Showing ${from}-${to} of ${formattedTotal} requests`;
    }

    return `${formattedTotal} requests`;
});

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
                search: normalizedSearch.value || undefined,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    selectedDepartment.value = 'all';
    selectedStatus.value = 'all';
    selectedModule.value = 'all';
    selectedAction.value = 'all';
    search.value = '';
    applyFilters();
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

const printApprovalVoucher = (approvalVoucherId: number) => {
    openPrintDialog(printVoucher(approvalVoucherId).url);
};
</script>

<template>
    <Head title="Requests" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <section
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 border-b border-border/70 px-4 py-4"
                >
                    <div class="min-w-0 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="flex items-center gap-2 text-base font-semibold tracking-tight text-foreground"
                            >
                                <ShieldCheck class="size-4 text-muted-foreground" />
                                Requests
                            </div>
                            <Badge variant="outline" class="rounded-md font-medium">
                                {{ currentScopeLabel }}
                            </Badge>
                            <Badge
                                variant="secondary"
                                class="rounded-md font-medium tabular-nums"
                            >
                                {{ approval_vouchers.meta.total.toLocaleString() }}
                                total
                            </Badge>
                            <Badge
                                variant="outline"
                                class="rounded-md font-medium tabular-nums"
                            >
                                <CheckCheck class="mr-1 size-3.5" />
                                {{ pendingReviewCount.toLocaleString() }} pending
                            </Badge>
                            <Badge
                                v-if="hasActiveFilters"
                                variant="outline"
                                class="rounded-md font-medium"
                            >
                                {{ activeFilterBadges.length }} filter{{
                                    activeFilterBadges.length === 1 ? '' : 's'
                                }}
                                active
                            </Badge>
                        </div>

                        <p class="max-w-4xl text-sm leading-6 text-muted-foreground">
                            Review requests waiting for approval or already
                            processed.
                        </p>

                        <div
                            v-if="hasActiveFilters"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="badge in activeFilterBadges"
                                :key="badge"
                                variant="outline"
                                class="rounded-md border-dashed px-2.5 py-1 text-xs font-normal text-muted-foreground"
                            >
                                {{ badge }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-4">
                    <div
                        class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="filter-approval-search"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Search
                            </Label>
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="filter-approval-search"
                                    v-model="search"
                                    type="text"
                                    placeholder="Request no., subject, preparer"
                                    class="pl-9"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                        </div>

                        <div
                            v-if="canSelectDepartment"
                            class="grid gap-1.5"
                        >
                            <Label
                                for="filter-approval-department"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Department
                            </Label>
                            <Select v-model="selectedDepartment">
                                <SelectTrigger id="filter-approval-department">
                                    <SelectValue placeholder="All departments" />
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

                        <div v-else class="grid gap-1.5">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Scope
                            </span>
                            <div
                                class="flex h-9 items-center gap-2 rounded-md border bg-muted/20 px-3 text-sm text-muted-foreground"
                            >
                                <Building2 class="size-4" />
                                <span class="truncate">{{ currentScopeLabel }}</span>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-approval-status"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Status
                            </Label>
                            <Select v-model="selectedStatus">
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

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-approval-module"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Module
                            </Label>
                            <Select v-model="selectedModule">
                                <SelectTrigger id="filter-approval-module">
                                    <SelectValue placeholder="All modules" />
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

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-approval-action"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Action
                            </Label>
                            <Select v-model="selectedAction">
                                <SelectTrigger id="filter-approval-action">
                                    <SelectValue placeholder="All actions" />
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

                        <div
                            class="flex items-end gap-2 md:col-span-2 xl:col-span-1 xl:justify-end"
                        >
                            <Button
                                class="flex-1 xl:flex-none"
                                :disabled="!filtersAreDirty"
                                @click="applyFilters"
                            >
                                Apply
                            </Button>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :disabled="!hasActiveFilters && !filtersAreDirty"
                                @click="resetFilters"
                            >
                                <RotateCcw class="size-4" />
                                <span class="sr-only">Reset filters</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm"
            >
                <div
                    class="flex flex-col gap-2 border-b border-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-foreground">
                            Request queue
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ resultsSummary }}
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge variant="outline" class="rounded-md font-medium">
                            {{ currentScopeLabel }}
                        </Badge>
                        <span>
                            {{
                                hasActiveFilters
                                    ? `${activeFilterBadges.length} active filter${
                                          activeFilterBadges.length === 1
                                              ? ''
                                              : 's'
                                      }`
                                    : 'No active filters'
                            }}
                        </span>
                    </div>
                </div>

                <div class="p-4">
                    <div
                        v-if="approval_vouchers.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        No requests found for these filters.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="approvalVoucher in approval_vouchers.data"
                                :key="`approval-card-${approvalVoucher.id}`"
                                class="rounded-lg border border-border/70 bg-background p-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-foreground">
                                            {{ approvalVoucher.subject }}
                                        </div>
                                        <div class="mt-1 text-xs text-muted-foreground">
                                            {{ approvalVoucher.voucher_no }}
                                        </div>
                                    </div>
                                    <Badge
                                        :variant="statusVariant(approvalVoucher.status)"
                                        class="rounded-md px-2 py-0.5"
                                    >
                                        {{ approvalVoucher.status_label }}
                                    </Badge>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Badge variant="outline" class="rounded-md">
                                        {{
                                            displayApprovalModuleLabel(
                                                approvalVoucher.module,
                                                approvalVoucher.module_label,
                                            )
                                        }}
                                    </Badge>
                                    <Badge
                                        :variant="agingVariant(approvalVoucher)"
                                        class="rounded-md"
                                    >
                                        {{ agingLabel(approvalVoucher) }}
                                    </Badge>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div class="text-sm text-muted-foreground">
                                        {{ approvalVoucher.action_label }}
                                    </div>
                                    <div
                                        v-if="canSelectDepartment"
                                        class="text-sm text-muted-foreground"
                                    >
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
                                        {{
                                            approvalVoucher.requested_by_user?.name ??
                                            '-'
                                        }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ rowDate(approvalVoucher) }}
                                    </div>
                                </div>

                                <ResponsiveActionGroup class="mt-3" align="end">
                                    <Button as-child variant="outline" size="sm">
                                        <a :href="downloadVoucher(approvalVoucher.id).url">
                                            <Download class="mr-2 size-4" />
                                            Download
                                        </a>
                                    </Button>
                                    <Button as-child variant="outline" size="sm">
                                        <Link :href="showVoucher(approvalVoucher.id).url">
                                            View
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="printApprovalVoucher(approvalVoucher.id)"
                                    >
                                        <Printer class="mr-2 size-4" />
                                        Print
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div class="hidden md:block">
                            <div
                                class="min-w-[86rem] overflow-hidden rounded-lg border border-border/70"
                            >
                                <Table>
                                    <TableHeader class="bg-muted/35">
                                        <TableRow class="hover:bg-transparent">
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Request no.
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Subject
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Module
                                            </TableHead>
                                            <TableHead
                                                v-if="canSelectDepartment"
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Department
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Preparer
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Status
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Aging
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Date
                                            </TableHead>
                                            <TableHead
                                                class="h-11 px-4 text-right text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            >
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="approvalVoucher in approval_vouchers.data"
                                            :key="approvalVoucher.id"
                                        >
                                            <TableCell
                                                class="w-[9rem] px-4 py-3 align-top font-medium"
                                            >
                                                {{ approvalVoucher.voucher_no }}
                                            </TableCell>
                                            <TableCell
                                                class="max-w-[24rem] px-4 py-3 align-top"
                                            >
                                                <div class="font-medium">
                                                    {{ approvalVoucher.subject }}
                                                </div>
                                                <div
                                                    class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                                                >
                                                    {{ approvalVoucher.action_label }}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                class="w-[10rem] px-4 py-3 align-top text-sm text-muted-foreground"
                                            >
                                                {{
                                                    displayApprovalModuleLabel(
                                                        approvalVoucher.module,
                                                        approvalVoucher.module_label,
                                                    )
                                                }}
                                            </TableCell>
                                            <TableCell
                                                v-if="canSelectDepartment"
                                                class="max-w-[14rem] px-4 py-3 align-top text-sm text-muted-foreground"
                                            >
                                                <div class="truncate">
                                                    {{
                                                        approvalVoucher.department
                                                            ? displayDepartmentName(
                                                                  approvalVoucher.department,
                                                                  approvalVoucher
                                                                      .department
                                                                      .name,
                                                              )
                                                            : '-'
                                                    }}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                class="w-[12rem] px-4 py-3 align-top text-sm text-muted-foreground"
                                            >
                                                {{
                                                    approvalVoucher.requested_by_user
                                                        ?.name ?? '-'
                                                }}
                                            </TableCell>
                                            <TableCell class="w-[8rem] px-4 py-3 align-top">
                                                <Badge
                                                    :variant="
                                                        statusVariant(
                                                            approvalVoucher.status,
                                                        )
                                                    "
                                                    class="rounded-md px-2 py-0.5"
                                                >
                                                    {{ approvalVoucher.status_label }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell class="w-[9rem] px-4 py-3 align-top">
                                                <Badge
                                                    :variant="
                                                        agingVariant(
                                                            approvalVoucher,
                                                        )
                                                    "
                                                    class="rounded-md px-2 py-0.5"
                                                >
                                                    {{ agingLabel(approvalVoucher) }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell
                                                class="w-[10rem] px-4 py-3 align-top text-sm text-muted-foreground"
                                            >
                                                {{ rowDate(approvalVoucher) }}
                                            </TableCell>
                                            <TableCell
                                                class="w-[8rem] px-4 py-3 align-top"
                                            >
                                                <div class="flex justify-end gap-1">
                                                    <Button
                                                        as-child
                                                        variant="ghost"
                                                        size="icon-sm"
                                                    >
                                                        <a
                                                            :href="downloadVoucher(approvalVoucher.id).url"
                                                            title="Download request"
                                                        >
                                                            <Download class="size-4" />
                                                            <span class="sr-only">
                                                                Download
                                                            </span>
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        as-child
                                                        variant="ghost"
                                                        size="icon-sm"
                                                    >
                                                        <Link
                                                            :href="showVoucher(approvalVoucher.id).url"
                                                            title="View request"
                                                        >
                                                            <Eye class="size-4" />
                                                            <span class="sr-only">
                                                                View
                                                            </span>
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        title="Print request"
                                                        @click="
                                                            printApprovalVoucher(
                                                                approvalVoucher.id,
                                                            )
                                                        "
                                                    >
                                                        <Printer class="size-4" />
                                                        <span class="sr-only">
                                                            Print
                                                        </span>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="approval_vouchers.meta.last_page > 1"
                    class="border-t border-border/70 px-4 py-3"
                >
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrev
                                    :href="approval_vouchers.links.prev"
                                    :disabled="!approval_vouchers.links.prev"
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
                                    :disabled="!approval_vouchers.links.next"
                                />
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
