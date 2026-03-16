<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Calendar, PieChart as PieIcon, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import PieChart from '@/components/charts/PieChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    ExpensesByCategoryRow,
    IncomeVsExpensesRow,
    ReportMonthOption,
    ReportTotals,
    SpendingTrendPoint,
} from '@/types';

const props = defineProps<{
    filters: {
        month: number;
        year: number;
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

const applyFilters = () => {
    router.get(
        index.url({
            query: {
                month: selectedMonth.value,
                year: selectedYear.value,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const expensesPie = computed(() => {
    return (props.breakdowns.expenses_by_category ?? []).map((row) => ({
        label: row.category_name,
        value: row.total,
    }));
});

const barLabels = computed(() => {
    return (props.charts.income_vs_expenses ?? []).map((row) =>
        new Date(selectedYear.value, row.month - 1, 1).toLocaleString(undefined, { month: 'short' }),
    );
});

const barSeries = computed(() => {
    const rows = props.charts.income_vs_expenses ?? [];

    return [
        { name: 'Income', values: rows.map((row) => row.income), color: '#22c55e' },
        { name: 'Expenses', values: rows.map((row) => row.expenses), color: '#f97316' },
    ];
});

const trendLabels = computed(() => {
    return (props.charts.spending_trend ?? []).map((point) => {
        const date = new Date(point.date);

        return String(date.getDate());
    });
});

const trendValues = computed(() => {
    return (props.charts.spending_trend ?? []).map((point) => point.expenses);
});

const budgetComparisonLabels = computed(() => {
    return (props.breakdowns.budget_vs_actual ?? []).map((budget) => budget.category_name);
});

const budgetComparisonSeries = computed(() => {
    const rows = props.breakdowns.budget_vs_actual ?? [];

    return [
        {
            name: 'Budget',
            values: rows.map((budget) => budget.amount_limit),
            color: '#0ea5e9',
        },
        {
            name: 'Actual',
            values: rows.map((budget) => budget.amount_spent),
            color: '#f97316',
        },
    ];
});

const monthTitle = computed(() => {
    const date = new Date(selectedYear.value, selectedMonth.value - 1, 1);

    return date.toLocaleString(undefined, { month: 'long', year: 'numeric' });
});
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <BarChart3 class="size-5" />
                            Reports
                        </CardTitle>
                        <CardDescription>
                            Monthly and yearly summaries for income and expenses.
                        </CardDescription>
                    </div>

                    <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                        <div class="grid gap-2">
                            <Label for="report-month" class="flex items-center gap-2">
                                <Calendar class="size-4" />
                                Month
                            </Label>
                            <Select v-model="selectedMonth">
                                <SelectTrigger id="report-month" class="w-full sm:w-[180px]">
                                    <SelectValue placeholder="Select month" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in options.months" :key="m.month" :value="m.month">
                                        {{ m.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="report-year">Year</Label>
                            <Select v-model="selectedYear">
                                <SelectTrigger id="report-year" class="w-full sm:w-[140px]">
                                    <SelectValue placeholder="Select year" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="y in options.years" :key="y" :value="y">
                                        {{ y }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button class="w-full sm:w-auto" variant="outline" @click="applyFilters">
                            Apply
                        </Button>
                    </div>
                </CardHeader>
            </Card>

            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-base">Monthly summary</CardTitle>
                        <CardDescription>{{ monthTitle }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="default">Income: {{ summary.monthly.income.toFixed(2) }}</Badge>
                            <Badge variant="secondary">Expenses: {{ summary.monthly.expenses.toFixed(2) }}</Badge>
                            <Badge :variant="summary.monthly.balance >= 0 ? 'outline' : 'destructive'">
                                Balance: {{ summary.monthly.balance.toFixed(2) }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-base">Yearly summary</CardTitle>
                        <CardDescription>{{ selectedYear }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="default">Income: {{ summary.yearly.income.toFixed(2) }}</Badge>
                            <Badge variant="secondary">Expenses: {{ summary.yearly.expenses.toFixed(2) }}</Badge>
                            <Badge :variant="summary.yearly.balance >= 0 ? 'outline' : 'destructive'">
                                Balance: {{ summary.yearly.balance.toFixed(2) }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <TrendingUp class="size-4" />
                            Chart-ready
                        </CardTitle>
                        <CardDescription>Props are structured for charts</CardDescription>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        Pie: expenses by category. Bar: income vs expenses. Line: spending trend.
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <PieIcon class="size-4" />
                            Expenses by category
                        </CardTitle>
                        <CardDescription>For {{ monthTitle }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PieChart :items="expensesPie" />
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Income vs expenses</CardTitle>
                        <CardDescription>Monthly totals for {{ selectedYear }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BarChart :labels="barLabels" :series="barSeries" />
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Spending trend</CardTitle>
                    <CardDescription>Daily expense totals for {{ monthTitle }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <LineChart :labels="trendLabels" :values="trendValues" color="#f97316" />
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Budget comparison</CardTitle>
                    <CardDescription>Budgeted versus actual spending for the selected month</CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="breakdowns.budget_vs_actual.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No budgets found for the selected month.
                    </div>

                    <div v-else class="space-y-4">
                        <BarChart :labels="budgetComparisonLabels" :series="budgetComparisonSeries" />

                        <div class="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="text-right">Budget</TableHead>
                                        <TableHead class="text-right">Actual</TableHead>
                                        <TableHead class="text-right">Remaining</TableHead>
                                        <TableHead class="text-right">Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="budget in breakdowns.budget_vs_actual" :key="budget.id">
                                        <TableCell class="font-medium">{{ budget.category_name }}</TableCell>
                                        <TableCell class="text-right tabular-nums">
                                            {{ budget.amount_limit.toFixed(2) }}
                                        </TableCell>
                                        <TableCell class="text-right tabular-nums">
                                            {{ budget.amount_spent.toFixed(2) }}
                                        </TableCell>
                                        <TableCell class="text-right tabular-nums">
                                            {{ budget.amount_remaining.toFixed(2) }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Badge :variant="budget.is_over_budget ? 'destructive' : budget.percentage_used >= 80 ? 'secondary' : 'outline'">
                                                {{ budget.is_over_budget ? 'Over budget' : budget.percentage_used >= 80 ? 'Near limit' : 'On track' }}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
