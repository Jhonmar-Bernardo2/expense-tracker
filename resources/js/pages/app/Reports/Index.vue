<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    PieChart as PieIcon,
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
import {
    displayDepartmentName,
    MONTHLY_BUDGET_LABEL,
} from '@/lib/plain-language';
import { dashboard } from '@/routes/app';
import { index } from '@/routes/app/reports';
import type {
    BudgetAccessShared,
    BudgetAllocation,
    BreadcrumbItem,
    Budget,
    DepartmentOption,
    DepartmentScope,
    ExpensesByCategoryRow,
    IncomeVsExpensesRow,
    ReportMonthOption,
    ReportTotals,
    SpendingTrendPoint,
} from '@/types';

const props = defineProps<{
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    filters: {
        month: number;
        year: number;
        department: number | null;
    };
    summary: {
        monthly: ReportTotals;
        yearly: ReportTotals;
    };
    breakdowns: {
        expenses_by_category: ExpensesByCategoryRow[];
        budget_vs_actual: Budget[];
    };
    charts: {
        income_vs_expenses: IncomeVsExpensesRow[];
        spending_trend: SpendingTrendPoint[];
    };
    options: {
        months: ReportMonthOption[];
        years: number[];
    };
    budget_summary: {
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
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Reports', href: index() },
];
const page = usePage();

const selectedMonth = ref(props.filters.month);
const selectedYear = ref(props.filters.year);
const selectedDepartment = ref<number | 'all'>(
    props.filters.department ?? 'all',
);

const canSelectDepartment = computed(
    () => props.department_scope.can_select_department,
);
const departmentLabel = computed(() =>
    props.department_scope.is_all_departments
        ? 'All departments'
        : displayDepartmentName(props.department_scope.selected_department),
);
const budgetAccess = computed(
    () => page.props.budget_access as BudgetAccessShared,
);

const summaryMetrics = computed(() => [
    {
        id: 'reports-monthly-income',
        label: 'Monthly income',
        value: formatCurrency(props.summary.monthly.income),
        helper: `Selected month: ${monthTitle.value}`,
        icon: TrendingUp,
        tone: 'success' as const,
    },
    {
        id: 'reports-monthly-expenses',
        label: 'Monthly expenses',
        value: formatCurrency(props.summary.monthly.expenses),
        helper: `Selected month: ${monthTitle.value}`,
        icon: TrendingDown,
        tone: 'warning' as const,
    },
    {
        id: 'reports-monthly-balance',
        label: 'Monthly balance',
        value: formatCurrency(props.summary.monthly.balance),
        helper: 'Income minus expenses for the selected month.',
        icon: Wallet,
        tone:
            props.summary.monthly.balance < 0
                ? ('danger' as const)
                : ('info' as const),
    },
    {
        id: 'reports-yearly-balance',
        label: 'Yearly balance',
        value: formatCurrency(props.summary.yearly.balance),
        helper: `Rolling total for ${selectedYear.value}.`,
        icon: BarChart3,
        tone:
            props.summary.yearly.balance < 0
                ? ('danger' as const)
                : ('info' as const),
    },
]);

const centralBudgetMetrics = computed(() => {
    if (props.budget_summary === null) {
        return [];
    }

    return [
        {
            id: 'reports-budget-approved',
            label: 'Approved monthly budget',
            value: formatCurrency(
                props.budget_summary.current_month_summary.approved_allocation,
            ),
            helper: 'Approved monthly budget for the Finance Team.',
            icon: Wallet,
            tone: 'info' as const,
        },
        {
            id: 'reports-budget-allocated',
            label: 'Budget set for categories',
            value: formatCurrency(
                props.budget_summary.current_month_summary.total_allocated,
            ),
            helper: 'Amount already set aside for categories.',
            icon: TrendingUp,
            tone: 'info' as const,
        },
        {
            id: 'reports-budget-spent',
            label: 'Spent',
            value: formatCurrency(
                props.budget_summary.current_month_summary.total_spent,
            ),
            helper: 'Approved spending for this period.',
            icon: TrendingDown,
            tone: 'warning' as const,
        },
        {
            id: 'reports-budget-remaining',
            label: 'Budget left',
            value: formatCurrency(
                props.budget_summary.current_month_summary.total_remaining,
            ),
            helper: 'Approved monthly budget minus spending.',
            icon: Wallet,
            tone:
                props.budget_summary.current_month_summary.total_remaining < 0
                    ? ('danger' as const)
                    : ('success' as const),
        },
    ];
});

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

const monthTitle = computed(() =>
    new Date(selectedYear.value, selectedMonth.value - 1, 1).toLocaleString(
        undefined,
        {
            month: 'long',
            year: 'numeric',
        },
    ),
);

const expensesPie = computed(() =>
    props.breakdowns.expenses_by_category.map((row) => ({
        label: row.category_name,
        value: row.total,
    })),
);

const yearlySeries = computed(() => [
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

const yearlyLabels = computed(() =>
    props.charts.income_vs_expenses.map((row) =>
        new Date(selectedYear.value, row.month - 1, 1).toLocaleString(
            undefined,
            { month: 'short' },
        ),
    ),
);

const spendingSeries = computed(() => [
    {
        name: 'Expenses',
        values: props.charts.spending_trend.map((point) => point.expenses),
        color: '#0f766e',
    },
]);

const spendingLabels = computed(() =>
    props.charts.spending_trend.map((point) => point.date.slice(-2)),
);

const budgetSeries = computed(() => [
    {
        name: MONTHLY_BUDGET_LABEL,
        values: props.breakdowns.budget_vs_actual.map(
            (budget) => budget.amount_limit,
        ),
        color: '#2563eb',
    },
    {
        name: 'Spent',
        values: props.breakdowns.budget_vs_actual.map(
            (budget) => budget.amount_spent,
        ),
        color: '#ea580c',
    },
]);

const budgetLabels = computed(() =>
    props.breakdowns.budget_vs_actual.map((budget) => budget.category_name),
);

const applyFilters = () => {
    router.get(
        index.url({
            query: {
                month: selectedMonth.value,
                year: selectedYear.value,
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
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <BarChart3 class="size-5" />
                            Reports
                        </CardTitle>
                        <CardDescription
                            >Compare department spending for
                            {{ monthTitle }}.</CardDescription
                        >
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-if="canSelectDepartment" class="grid gap-2">
                            <Label for="report-department">Department</Label>
                            <Select v-model="selectedDepartment">
                                <SelectTrigger
                                    id="report-department"
                                    class="w-full sm:w-[200px]"
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

                        <div
                            v-else
                            class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        >
                            <Building2 class="size-4" />
                            {{ departmentLabel }}
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-month">Month</Label>
                            <Select v-model="selectedMonth">
                                <SelectTrigger
                                    id="report-month"
                                    class="w-full sm:w-[160px]"
                                >
                                    <SelectValue placeholder="Select month" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="month in options.months"
                                        :key="month.month"
                                        :value="month.month"
                                    >
                                        {{ month.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-year">Year</Label>
                            <Select v-model="selectedYear">
                                <SelectTrigger
                                    id="report-year"
                                    class="w-full sm:w-[140px]"
                                >
                                    <SelectValue placeholder="Select year" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="year in options.years"
                                        :key="year"
                                        :value="year"
                                    >
                                        {{ year }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <ResponsiveActionGroup align="end">
                        <Button variant="outline" @click="applyFilters">
                            Show results
                        </Button>
                    </ResponsiveActionGroup>
                </CardContent>
            </Card>

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

            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Income vs Expenses</CardTitle>
                        <CardDescription
                            >{{ selectedYear }} overview</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            :labels="yearlyLabels"
                            :series="yearlySeries"
                        />
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <PieIcon class="size-4" />
                                Expenses by Category
                            </CardTitle>
                            <CardDescription>{{
                                departmentLabel
                            }}</CardDescription>
                        </div>
                        <Badge variant="outline"
                            >{{ expensesPie.length }} categories</Badge
                        >
                    </CardHeader>
                    <CardContent>
                        <PieChart :items="expensesPie" />
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Spending Trend</CardTitle>
                        <CardDescription
                            >Daily expenses for
                            {{ monthTitle }}</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            :labels="spendingLabels"
                            :series="spendingSeries"
                        />
                    </CardContent>
                </Card>

                <Card
                    v-if="
                        budgetAccess.can_view_summaries &&
                        budget_summary !== null
                    "
                    class="border-sidebar-border/70 shadow-sm"
                >
                    <CardHeader>
                        <CardTitle>Budget vs Spending</CardTitle>
                        <CardDescription
                            >{{ budget_summary.scope_label }} comparison across
                            the organization.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <DashboardMetricGrid class="mb-4">
                            <DashboardMetricCard
                                v-for="metric in centralBudgetMetrics"
                                :key="metric.id"
                                :label="metric.label"
                                :value="metric.value"
                                :helper="metric.helper"
                                :icon="metric.icon"
                                :tone="metric.tone"
                            />
                        </DashboardMetricGrid>

                        <div
                            v-if="breakdowns.budget_vs_actual.length === 0"
                            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                        >
                            No budget data for this period.
                        </div>
                        <BarChart
                            v-else
                            :labels="budgetLabels"
                            :series="budgetSeries"
                        />
                    </CardContent>
                </Card>
            </div>

            <Card
                v-if="
                    budgetAccess.can_view_summaries && budget_summary !== null
                "
                class="border-sidebar-border/70 shadow-sm"
            >
                <CardHeader>
                    <CardTitle>Budget details</CardTitle>
                    <CardDescription
                        >{{ budget_summary.scope_label }} rows for
                        {{ monthTitle }}</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="breakdowns.budget_vs_actual.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No budget data for this period.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead>Category</TableHead>
                                    <TableHead class="text-right"
                                        >Monthly budget</TableHead
                                    >
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
                                    v-for="budget in breakdowns.budget_vs_actual"
                                    :key="budget.id"
                                >
                                    <TableCell>{{
                                        budget.category_name
                                    }}</TableCell>
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            formatCurrency(budget.amount_limit)
                                        }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            formatCurrency(budget.amount_spent)
                                        }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            formatCurrency(
                                                budget.amount_remaining,
                                            )
                                        }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
