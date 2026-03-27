<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CheckCheck,
    FileText,
    Landmark,
    PiggyBank,
    Receipt,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import PieChart from '@/components/charts/PieChart.vue';
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
import { Label } from '@/components/ui/label';
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
import type {
    ApprovalVoucher,
    Budget,
    BudgetAllocation,
    BreadcrumbItem,
    DashboardAction,
    DashboardApprovalSection,
    DashboardMetric,
    DashboardView,
    DashboardViewMode,
    DepartmentOption,
    DepartmentScope,
    ExpensesByCategoryRow,
    IncomeVsExpensesRow,
    Transaction,
} from '@/types';

const props = defineProps<{
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    totals: {
        income: number;
        expenses: number;
        balance: number;
    };
    current_month: {
        month: number;
        year: number;
        income: number;
        expenses: number;
        balance: number;
    };
    budgets: {
        scope_label: string;
        financial_management_department: DepartmentOption;
        active_allocation: BudgetAllocation | null;
        current_month_summary: {
            approved_allocation: number;
            total_allocated: number;
            total_unallocated: number;
            total_budgeted: number;
            total_spent: number;
            total_remaining: number;
            categories_over_budget: number;
        };
        current_month_statuses: Budget[];
    } | null;
    recent_transactions: Transaction[];
    charts: {
        expenses_by_category: ExpensesByCategoryRow[];
        income_vs_expenses: IncomeVsExpensesRow[];
    };
    dashboard_view: DashboardView;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

const selectedDepartment = ref<number | 'all'>(
    props.department_scope.department_id ?? 'all',
);

const canSelectDepartment = computed(
    () => props.department_scope.can_select_department,
);
const departmentLabel = computed(() =>
    props.department_scope.is_all_departments
        ? 'All departments'
        : (props.department_scope.selected_department?.name ??
          'Assigned department'),
);
const monthLabel = computed(() =>
    new Date(
        props.current_month.year,
        props.current_month.month - 1,
        1,
    ).toLocaleString(undefined, {
        month: 'long',
        year: 'numeric',
    }),
);
const viewMode = computed(
    () => props.dashboard_view.mode as DashboardViewMode,
);

const expensesPie = computed(() =>
    props.charts.expenses_by_category.map((row) => ({
        label: row.category_name,
        value: row.total,
    })),
);

const cashFlowLabels = computed(() =>
    props.charts.income_vs_expenses.map((row) =>
        new Date(props.current_month.year, row.month - 1, 1).toLocaleString(
            undefined,
            { month: 'short' },
        ),
    ),
);

const cashFlowSeries = computed(() => [
    {
        name: 'Income',
        values: props.charts.income_vs_expenses.map((row) => row.income),
        color: '#22c55e',
    },
    {
        name: 'Expenses',
        values: props.charts.income_vs_expenses.map((row) => row.expenses),
        color: '#f97316',
    },
]);

const primaryApprovalSection = computed(
    () =>
        props.dashboard_view.primary_section as DashboardApprovalSection | null,
);
const secondaryApprovalSection = computed(
    () =>
        props.dashboard_view.secondary_section as DashboardApprovalSection | null,
);

const chartHeadings = computed(() => {
    if (viewMode.value === 'admin') {
        return {
            cashFlowTitle: 'Organization Cash Flow',
            cashFlowDescription:
                'Income and expenses across the selected oversight scope.',
            expensesTitle: 'Cross-Department Spending',
            expensesDescription:
                'Category mix for approved spending in the selected scope.',
        };
    }

    if (viewMode.value === 'financial_management') {
        return {
            cashFlowTitle: 'Operating Cash Flow Context',
            cashFlowDescription:
                'Cash movement trends that support finance operations this year.',
            expensesTitle: 'Organization Spending Mix',
            expensesDescription:
                'Approved expense distribution across the organization.',
        };
    }

    return {
        cashFlowTitle: 'Department Cash Flow',
        cashFlowDescription:
            'Income and expenses for your assigned department this year.',
        expensesTitle: 'Department Spending Mix',
        expensesDescription:
            'Approved expense categories for your department this month.',
    };
});

const financeMetrics = computed<DashboardMetric[]>(() => {
    if (props.budgets === null) {
        return [];
    }

    return [
        {
            id: 'central-approved-allocation',
            label: 'Approved allocation',
            value: props.budgets.current_month_summary.approved_allocation,
            format: 'currency',
            helper: 'Current approved monthly total.',
            tone: 'info',
        },
        {
            id: 'central-allocated',
            label: 'Allocated to categories',
            value: props.budgets.current_month_summary.total_allocated,
            format: 'currency',
            helper: 'Already assigned to category budgets.',
            tone: 'info',
        },
        {
            id: 'central-unallocated',
            label: 'Unallocated',
            value: props.budgets.current_month_summary.total_unallocated,
            format: 'currency',
            helper: 'Still available to assign.',
            tone: 'warning',
        },
        {
            id: 'central-spent',
            label: 'Spent organization-wide',
            value: props.budgets.current_month_summary.total_spent,
            format: 'currency',
            helper: 'Approved expense transactions for the month.',
            tone: 'warning',
        },
        {
            id: 'central-remaining',
            label: 'Remaining after spending',
            value: props.budgets.current_month_summary.total_remaining,
            format: 'currency',
            helper: 'Approved allocation minus actual spending.',
            tone:
                props.budgets.current_month_summary.total_remaining < 0
                    ? 'danger'
                    : 'success',
        },
    ];
});

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

const formatMetricValue = (metric: DashboardMetric) => {
    if (metric.format === 'currency') {
        return formatCurrency(Number(metric.value ?? 0));
    }

    if (metric.format === 'percentage') {
        return `${Number(metric.value ?? 0).toFixed(0)}%`;
    }

    if (metric.format === 'number') {
        return Number(metric.value ?? 0).toLocaleString();
    }

    return String(metric.value ?? '');
};

const metricIcon = (metricId: string) =>
    ({
        'total-income': TrendingUp,
        'total-expenses': TrendingDown,
        'net-balance': Wallet,
        'pending-allocation-approvals': CheckCheck,
        'approved-allocation': PiggyBank,
        'allocated-categories': PiggyBank,
        unallocated: PiggyBank,
        spent: TrendingDown,
        remaining: Wallet,
        'pending-transaction-approvals': FileText,
        'my-pending-requests': FileText,
        'my-approved-requests': CheckCheck,
        'my-rejected-requests': Receipt,
        'department-expenses': Receipt,
        'central-approved-allocation': PiggyBank,
        'central-allocated': PiggyBank,
        'central-unallocated': PiggyBank,
        'central-spent': TrendingDown,
        'central-remaining': Wallet,
    })[metricId] ?? Landmark;

const quickActionIcon = (action: DashboardAction) =>
    ({
        receipt: Receipt,
        'file-text': FileText,
        'piggy-bank': PiggyBank,
        'bar-chart-3': BarChart3,
    })[action.icon ?? ''] ?? null;

const approvalStatusVariant = (voucher: ApprovalVoucher) => {
    if (voucher.status === 'approved') {
        return 'outline' as const;
    }

    if (voucher.status === 'rejected') {
        return 'destructive' as const;
    }

    return voucher.is_overdue ? ('destructive' as const) : ('secondary' as const);
};

const applyDepartmentFilter = () => {
    router.get(
        dashboard.url({
            query: {
                department:
                    selectedDepartment.value === 'all'
                        ? undefined
                        : selectedDepartment.value,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <Landmark class="size-5" />
                            {{ dashboard_view.title }}
                        </CardTitle>
                        <CardDescription class="max-w-3xl leading-relaxed">
                            {{ dashboard_view.description }}
                        </CardDescription>
                    </div>

                    <div class="flex w-full flex-col gap-4 xl:w-auto xl:min-w-[320px]">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-end"
                        >
                            <div v-if="canSelectDepartment" class="grid gap-2">
                                <Label for="dashboard-department"
                                    >Department</Label
                                >
                                <Select v-model="selectedDepartment">
                                    <SelectTrigger
                                        id="dashboard-department"
                                        class="w-full sm:w-[220px]"
                                    >
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

                            <div
                                v-else
                                class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                            >
                                <Building2 class="size-4" />
                                {{ departmentLabel }}
                            </div>

                            <Button variant="outline" @click="applyDepartmentFilter"
                                >Apply</Button
                            >
                        </div>

                        <ResponsiveActionGroup align="end">
                            <Button
                                v-for="action in dashboard_view.quick_actions"
                                :key="action.id"
                                :variant="action.variant"
                                as-child
                            >
                                <Link :href="action.href">
                                    <component
                                        :is="quickActionIcon(action)"
                                        v-if="quickActionIcon(action)"
                                        class="size-4"
                                    />
                                    {{ action.label }}
                                </Link>
                            </Button>
                        </ResponsiveActionGroup>
                    </div>
                </CardHeader>
            </Card>

            <DashboardMetricGrid>
                <DashboardMetricCard
                    v-for="metric in dashboard_view.primary_metrics"
                    :key="metric.id"
                    :label="metric.label"
                    :value="formatMetricValue(metric)"
                    :helper="metric.helper"
                    :icon="metricIcon(metric.id)"
                    :tone="metric.tone"
                />
            </DashboardMetricGrid>

            <template v-if="viewMode === 'admin'">
                <div class="grid gap-4 xl:grid-cols-2">
                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BarChart3 class="size-4" />
                                {{ chartHeadings.cashFlowTitle }}
                            </CardTitle>
                            <CardDescription>
                                {{ chartHeadings.cashFlowDescription }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BarChart
                                :labels="cashFlowLabels"
                                :series="cashFlowSeries"
                            />
                        </CardContent>
                    </Card>

                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>
                                {{ chartHeadings.expensesTitle }}
                            </CardTitle>
                            <CardDescription>
                                {{ chartHeadings.expensesDescription }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PieChart :items="expensesPie" />
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <Card
                        v-if="primaryApprovalSection"
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>{{ primaryApprovalSection.title }}</CardTitle>
                            <CardDescription>
                                {{ primaryApprovalSection.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="primaryApprovalSection.items.length === 0"
                                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                            >
                                {{ primaryApprovalSection.empty_message }}
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in primaryApprovalSection.items"
                                    :key="item.id"
                                    class="rounded-xl border p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div class="min-w-0 space-y-2">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <Badge
                                                    :variant="
                                                        approvalStatusVariant(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    {{ item.status_label }}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {{ item.module_label }}
                                                </Badge>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ item.voucher_no }}
                                                </span>
                                            </div>
                                            <div class="font-medium">
                                                {{ item.subject }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    item.department?.name ??
                                                    'Unassigned department'
                                                }}
                                            </div>
                                        </div>
                                        <Button variant="outline" as-child>
                                            <Link
                                                :href="`/approval-vouchers/${item.id}`"
                                            >
                                                Review
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        v-if="budgets !== null"
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>Central Finance Health</CardTitle>
                            <CardDescription>
                                {{ budgets.scope_label }} for {{ monthLabel }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <DashboardMetricGrid>
                                <DashboardMetricCard
                                    v-for="metric in financeMetrics"
                                    :key="metric.id"
                                    :label="metric.label"
                                    :value="formatMetricValue(metric)"
                                    :helper="metric.helper"
                                    :icon="metricIcon(metric.id)"
                                    :tone="metric.tone"
                                />
                            </DashboardMetricGrid>

                            <div
                                v-if="budgets.current_month_statuses.length === 0"
                                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                            >
                                No category budgets found for this month.
                            </div>

                            <Table v-else>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="text-right"
                                            >Limit</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Spent</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Remaining</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Status</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="budget in budgets.current_month_statuses"
                                        :key="budget.id"
                                    >
                                        <TableCell>{{
                                            budget.category_name
                                        }}</TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{
                                                formatCurrency(
                                                    budget.amount_limit,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{
                                                formatCurrency(
                                                    budget.amount_spent,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{
                                                formatCurrency(
                                                    budget.amount_remaining,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Badge
                                                :variant="
                                                    budget.is_over_budget
                                                        ? 'destructive'
                                                        : budget.percentage_used >=
                                                            80
                                                          ? 'secondary'
                                                          : 'outline'
                                                "
                                            >
                                                {{
                                                    budget.is_over_budget
                                                        ? 'Over budget'
                                                        : budget.percentage_used >=
                                                            80
                                                          ? 'Near limit'
                                                          : 'On track'
                                                }}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Receipt class="size-4" />
                            Recent Transactions
                        </CardTitle>
                        <CardDescription>
                            {{ departmentLabel }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="recent_transactions.length === 0"
                            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                        >
                            No recent transactions found.
                        </div>

                        <Table v-else>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead v-if="canSelectDepartment"
                                        >Department</TableHead
                                    >
                                    <TableHead>Title</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead class="text-right"
                                        >Amount</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="transaction in recent_transactions"
                                    :key="transaction.id"
                                >
                                    <TableCell>{{
                                        transaction.transaction_date ?? '-'
                                    }}</TableCell>
                                    <TableCell v-if="canSelectDepartment">{{
                                        transaction.department?.name ?? '-'
                                    }}</TableCell>
                                    <TableCell>{{ transaction.title }}</TableCell>
                                    <TableCell>{{
                                        transaction.category?.name ?? '-'
                                    }}</TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{
                                            formatCurrency(
                                                Number(transaction.amount),
                                            )
                                        }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </template>

            <template v-else-if="viewMode === 'financial_management'">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                    <Card
                        v-if="primaryApprovalSection"
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>{{ primaryApprovalSection.title }}</CardTitle>
                            <CardDescription>
                                {{ primaryApprovalSection.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="primaryApprovalSection.items.length === 0"
                                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                            >
                                {{ primaryApprovalSection.empty_message }}
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in primaryApprovalSection.items"
                                    :key="item.id"
                                    class="rounded-xl border p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div class="min-w-0 space-y-2">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <Badge
                                                    :variant="
                                                        approvalStatusVariant(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    {{ item.status_label }}
                                                </Badge>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ item.voucher_no }}
                                                </span>
                                            </div>
                                            <div class="font-medium">
                                                {{ item.subject }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    item.department?.name ??
                                                    'Unassigned department'
                                                }}
                                            </div>
                                        </div>
                                        <Button variant="outline" as-child>
                                            <Link
                                                :href="`/approval-vouchers/${item.id}`"
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        v-if="budgets !== null"
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>Central Finance Status</CardTitle>
                            <CardDescription>
                                {{ budgets.scope_label }} for {{ monthLabel }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <DashboardMetricGrid>
                                <DashboardMetricCard
                                    v-for="metric in financeMetrics"
                                    :key="metric.id"
                                    :label="metric.label"
                                    :value="formatMetricValue(metric)"
                                    :helper="metric.helper"
                                    :icon="metricIcon(metric.id)"
                                    :tone="metric.tone"
                                />
                            </DashboardMetricGrid>

                            <Table
                                v-if="budgets.current_month_statuses.length > 0"
                            >
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="text-right"
                                            >Spent</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Remaining</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="budget in budgets.current_month_statuses"
                                        :key="budget.id"
                                    >
                                        <TableCell>{{
                                            budget.category_name
                                        }}</TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{
                                                formatCurrency(
                                                    budget.amount_spent,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{
                                                formatCurrency(
                                                    budget.amount_remaining,
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>{{ chartHeadings.expensesTitle }}</CardTitle>
                            <CardDescription>
                                {{ chartHeadings.expensesDescription }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PieChart :items="expensesPie" />
                        </CardContent>
                    </Card>

                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Receipt class="size-4" />
                                Recent Transactions
                            </CardTitle>
                            <CardDescription>
                                {{ departmentLabel }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="recent_transactions.length === 0"
                                class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                            >
                                No recent transactions found.
                            </div>

                            <Table v-else>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead class="text-right"
                                            >Amount</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="transaction in recent_transactions"
                                        :key="transaction.id"
                                    >
                                        <TableCell>{{
                                            transaction.transaction_date ?? '-'
                                        }}</TableCell>
                                        <TableCell>{{
                                            transaction.department?.name ?? '-'
                                        }}</TableCell>
                                        <TableCell>{{ transaction.title }}</TableCell>
                                        <TableCell class="text-right tabular-nums">
                                            {{
                                                formatCurrency(
                                                    Number(transaction.amount),
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <Card
                    v-if="secondaryApprovalSection"
                    class="border-sidebar-border/70 shadow-sm"
                >
                    <CardHeader>
                        <CardTitle>{{ secondaryApprovalSection.title }}</CardTitle>
                        <CardDescription>
                            {{ secondaryApprovalSection.description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="secondaryApprovalSection.items.length === 0"
                            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            {{ secondaryApprovalSection.empty_message }}
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="item in secondaryApprovalSection.items"
                                :key="item.id"
                                class="rounded-xl border p-4"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="min-w-0 space-y-2">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <Badge
                                                :variant="
                                                    approvalStatusVariant(item)
                                                "
                                            >
                                                {{ item.status_label }}
                                            </Badge>
                                            <Badge variant="outline">
                                                {{ item.action_label }}
                                            </Badge>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.voucher_no }}
                                            </span>
                                        </div>
                                        <div class="font-medium">
                                            {{ item.subject }}
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                item.department?.name ??
                                                'Unassigned department'
                                            }}
                                        </div>
                                    </div>
                                    <Button variant="outline" as-child>
                                        <Link
                                            :href="`/approval-vouchers/${item.id}`"
                                        >
                                            View request
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <template v-else>
                <div class="grid gap-4 xl:grid-cols-2">
                    <Card
                        v-if="primaryApprovalSection"
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>{{ primaryApprovalSection.title }}</CardTitle>
                            <CardDescription>
                                {{ primaryApprovalSection.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="primaryApprovalSection.items.length === 0"
                                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                            >
                                {{ primaryApprovalSection.empty_message }}
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in primaryApprovalSection.items"
                                    :key="item.id"
                                    class="rounded-xl border p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div class="min-w-0 space-y-2">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <Badge
                                                    :variant="
                                                        approvalStatusVariant(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    {{ item.status_label }}
                                                </Badge>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ item.voucher_no }}
                                                </span>
                                            </div>
                                            <div class="font-medium">
                                                {{ item.subject }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ item.module_label }} request
                                            </div>
                                        </div>
                                        <Button variant="outline" as-child>
                                            <Link
                                                :href="`/approval-vouchers/${item.id}`"
                                            >
                                                View status
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Receipt class="size-4" />
                                Recent Department Transactions
                            </CardTitle>
                            <CardDescription>
                                {{ departmentLabel }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="recent_transactions.length === 0"
                                class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                            >
                                No recent transactions found.
                            </div>

                            <Table v-else>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="text-right"
                                            >Amount</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="transaction in recent_transactions"
                                        :key="transaction.id"
                                    >
                                        <TableCell>{{
                                            transaction.transaction_date ?? '-'
                                        }}</TableCell>
                                        <TableCell>{{ transaction.title }}</TableCell>
                                        <TableCell>{{
                                            transaction.category?.name ?? '-'
                                        }}</TableCell>
                                        <TableCell class="text-right tabular-nums">
                                            {{
                                                formatCurrency(
                                                    Number(transaction.amount),
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>{{ chartHeadings.expensesTitle }}</CardTitle>
                            <CardDescription>
                                {{ chartHeadings.expensesDescription }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PieChart :items="expensesPie" />
                        </CardContent>
                    </Card>

                    <Card class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BarChart3 class="size-4" />
                                {{ chartHeadings.cashFlowTitle }}
                            </CardTitle>
                            <CardDescription>
                                {{ chartHeadings.cashFlowDescription }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BarChart
                                :labels="cashFlowLabels"
                                :series="cashFlowSeries"
                            />
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
