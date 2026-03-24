<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { index } from '@/routes/reports';
import type {
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
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Reports', href: index() },
];

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
        : (props.department_scope.selected_department?.name ??
          'Assigned department'),
);

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
        name: 'Budget',
        values: props.breakdowns.budget_vs_actual.map(
            (budget) => budget.amount_limit,
        ),
        color: '#2563eb',
    },
    {
        name: 'Actual',
        values: props.breakdowns.budget_vs_actual.map(
            (budget) => budget.amount_spent,
        ),
        color: '#ea580c',
    },
]);

const budgetLabels = computed(() =>
    props.breakdowns.budget_vs_actual.map((budget) =>
        canSelectDepartment.value
            ? `${budget.department?.name ?? 'Department'} · ${budget.category_name}`
            : budget.category_name,
    ),
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
                            >Compare department performance for
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
                <CardContent class="flex justify-end">
                    <Button variant="outline" @click="applyFilters"
                        >Apply Filters</Button
                    >
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Monthly Income</CardTitle>
                        <TrendingUp class="size-4 text-emerald-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(summary.monthly.income)
                    }}</CardContent>
                </Card>
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Monthly Expenses</CardTitle>
                        <TrendingDown class="size-4 text-orange-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(summary.monthly.expenses)
                    }}</CardContent>
                </Card>
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Monthly Balance</CardTitle>
                        <Wallet class="size-4 text-sky-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(summary.monthly.balance)
                    }}</CardContent>
                </Card>
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Yearly Balance</CardTitle>
                        <BarChart3 class="size-4 text-violet-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(summary.yearly.balance)
                    }}</CardContent>
                </Card>
            </div>

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

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Budget vs Actual</CardTitle>
                        <CardDescription
                            >Department-scoped budget
                            comparison</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="breakdowns.budget_vs_actual.length === 0"
                            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                        >
                            No budgets found for the selected period.
                        </div>
                        <BarChart
                            v-else
                            :labels="budgetLabels"
                            :series="budgetSeries"
                        />
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Budget Analysis</CardTitle>
                    <CardDescription
                        >{{ breakdowns.budget_vs_actual.length }} budget
                        rows</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="breakdowns.budget_vs_actual.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No budgets found for the selected period.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead v-if="canSelectDepartment"
                                        >Department</TableHead
                                    >
                                    <TableHead>Category</TableHead>
                                    <TableHead class="text-right"
                                        >Budget</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Actual</TableHead
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
                                    <TableCell v-if="canSelectDepartment">{{
                                        budget.department?.name ?? '-'
                                    }}</TableCell>
                                    <TableCell>{{
                                        budget.category_name
                                    }}</TableCell>
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            budget.amount_limit.toFixed(2)
                                        }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            budget.amount_spent.toFixed(2)
                                        }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            budget.amount_remaining.toFixed(2)
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
