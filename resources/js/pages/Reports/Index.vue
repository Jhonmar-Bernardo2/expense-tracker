<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Calendar, PieChart as PieIcon, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { VisArea, VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import BarChart from '@/components/charts/BarChart.vue';
import PieChart from '@/components/charts/PieChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ChartContainer,
    ChartCrosshair,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
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
import type { ChartConfig } from '@/components/ui/chart';
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

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

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

const expensesPieDisplay = computed(() => {
    const items = [...expensesPie.value].sort((a, b) => b.value - a.value);

    if (items.length <= 5) {
        return items;
    }

    const primary = items.slice(0, 5);
    const othersTotal = items.slice(5).reduce((sum, item) => sum + item.value, 0);

    return [
        ...primary,
        {
            label: 'Others',
            value: othersTotal,
        },
    ];
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

const topExpenseCategory = computed(() => expensesPie.value[0] ?? null);

type SpendingTrendChartPoint = {
    date: Date;
    expenses: number;
};

const spendingTrendChartConfig = {
    expenses: {
        label: 'Expenses',
        color: 'hsl(24 95% 58%)',
    },
} satisfies ChartConfig;

const spendingTrendChartData = computed<SpendingTrendChartPoint[]>(() => {
    return (props.charts.spending_trend ?? []).map((point) => ({
        date: new Date(point.date),
        expenses: point.expenses,
    }));
});

const spendingTrendSvgDefs = `
  <linearGradient id="spendingTrendFill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-expenses)" stop-opacity="0.35" />
    <stop offset="95%" stop-color="var(--color-expenses)" stop-opacity="0.04" />
  </linearGradient>
`;

const spendingTrendMax = computed(() => {
    const max = Math.max(0, ...spendingTrendChartData.value.map((point) => point.expenses));

    return max > 0 ? Math.ceil(max * 1.15) : 100;
});

const activeSpendingDays = computed(() => spendingTrendChartData.value.filter((point) => point.expenses > 0).length);

const averageDailySpend = computed(() => {
    if (spendingTrendChartData.value.length === 0) {
        return 0;
    }

    const total = spendingTrendChartData.value.reduce((sum, point) => sum + point.expenses, 0);
    return total / spendingTrendChartData.value.length;
});

const highestSpendDay = computed(() => {
    const [highest] = [...spendingTrendChartData.value].sort((a, b) => b.expenses - a.expenses);
    return highest ?? null;
});
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-6">
            <section class="rounded-3xl border border-sidebar-border/60 bg-gradient-to-br from-background via-muted/10 to-muted/30 p-6 shadow-sm">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            <BarChart3 class="size-3.5" />
                            Reports
                        </div>
                        <div>
                            <h1 class="text-3xl font-semibold tracking-tight">Use reports for comparison, trends, and deeper review.</h1>
                            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                                Unlike the dashboard overview, this page focuses on selected-period analysis for {{ monthTitle }}, yearly movement, category concentration, and budget variance.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[480px]">
                        <div class="rounded-2xl border bg-background p-4 shadow-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Selected Month</div>
                            <div class="mt-2 text-lg font-semibold">{{ monthTitle }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">Primary reporting period</div>
                        </div>
                        <div class="rounded-2xl border bg-background p-4 shadow-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Top Expense</div>
                            <div class="mt-2 text-lg font-semibold">{{ topExpenseCategory?.label ?? 'No data' }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ topExpenseCategory ? formatCurrency(topExpenseCategory.value) : 'No expense category recorded' }}
                            </div>
                        </div>
                        <div class="rounded-2xl border bg-background p-4 shadow-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Focus Year</div>
                            <div class="mt-2 text-lg font-semibold">{{ selectedYear }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">Used across yearly comparisons</div>
                        </div>
                    </div>
                </div>
            </section>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-1.5">
                        <CardTitle class="text-xl">Report Filters</CardTitle>
                        <CardDescription>Choose a period, then refresh all charts and tables below.</CardDescription>
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

                        <Button class="w-full rounded-full px-6 sm:w-auto" variant="outline" @click="applyFilters">
                            Apply
                        </Button>
                    </div>
                </CardHeader>
            </Card>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm">
                    <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500/55" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Monthly Income</CardTitle>
                        <div class="rounded-full bg-emerald-500/10 p-2">
                            <TrendingUp class="size-4 text-emerald-700 dark:text-emerald-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight text-foreground">
                            {{ formatCurrency(summary.monthly.income) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ monthTitle }}</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm">
                    <div class="absolute inset-x-0 top-0 h-1 bg-orange-500/55" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Monthly Expenses</CardTitle>
                        <div class="rounded-full bg-orange-500/10 p-2">
                            <TrendingDown class="size-4 text-orange-700 dark:text-orange-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight text-foreground">
                            {{ formatCurrency(summary.monthly.expenses) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Selected month outflow</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm">
                    <div class="absolute inset-x-0 top-0 h-1 bg-sky-500/45" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Monthly Balance</CardTitle>
                        <div class="rounded-full bg-sky-500/10 p-2">
                            <Wallet class="size-4 text-sky-700 dark:text-sky-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight" :class="summary.monthly.balance >= 0 ? 'text-foreground' : 'text-rose-700 dark:text-rose-400'">
                            {{ formatCurrency(summary.monthly.balance) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Net result for the month</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm">
                    <div class="absolute inset-x-0 top-0 h-1 bg-violet-500/45" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Yearly Balance</CardTitle>
                        <div class="rounded-full bg-violet-500/10 p-2">
                            <BarChart3 class="size-4 text-violet-700 dark:text-violet-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight" :class="summary.yearly.balance >= 0 ? 'text-foreground' : 'text-rose-700 dark:text-rose-400'">
                            {{ formatCurrency(summary.yearly.balance) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ selectedYear }} overall performance</p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <CardTitle>Income vs Expenses</CardTitle>
                            <CardDescription>Monthly totals for {{ selectedYear }}</CardDescription>
                        </div>
                        <Badge variant="outline" class="w-fit rounded-full px-3 py-1 text-[11px]">
                            Yearly comparison
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-2xl border bg-background p-4">
                            <BarChart :labels="barLabels" :series="barSeries" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <PieIcon class="size-4" />
                                Expenses by Category
                            </CardTitle>
                            <CardDescription>Top categories are highlighted and smaller entries are grouped into Others</CardDescription>
                        </div>
                        <Badge variant="outline" class="w-fit rounded-full px-3 py-1 text-[11px]">
                            {{ expensesPieDisplay.length }} groups shown
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-2xl border bg-background p-4">
                            <PieChart :items="expensesPieDisplay" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Spending Trend</CardTitle>
                        <CardDescription>Daily expense totals for {{ monthTitle }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6 rounded-2xl border bg-background p-4">
                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="rounded-xl border bg-muted/10 p-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Highest Day</div>
                                    <div class="mt-2 text-base font-semibold">
                                        {{ highestSpendDay ? highestSpendDay.date.toLocaleString(undefined, { month: 'short', day: 'numeric' }) : 'No data' }}
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ highestSpendDay ? formatCurrency(highestSpendDay.expenses) : 'No spending recorded' }}
                                    </div>
                                </div>
                                <div class="rounded-xl border bg-muted/10 p-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Average Daily Spend</div>
                                    <div class="mt-2 text-base font-semibold">{{ formatCurrency(averageDailySpend) }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">Across all calendar days in the selected month</div>
                                </div>
                                <div class="rounded-xl border bg-muted/10 p-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Active Days</div>
                                    <div class="mt-2 text-base font-semibold">{{ activeSpendingDays }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">Days with at least one expense recorded</div>
                                </div>
                            </div>

                            <div class="h-[320px] w-full">
                                <ChartContainer :config="spendingTrendChartConfig" class="h-full w-full" :cursor="false">
                                    <VisXYContainer
                                        :data="spendingTrendChartData"
                                        :svg-defs="spendingTrendSvgDefs"
                                        :margin="{ top: 16, right: 12, bottom: 12, left: 0 }"
                                        :y-domain="[0, spendingTrendMax]"
                                    >
                                        <VisArea
                                            :x="(d: SpendingTrendChartPoint) => d.date"
                                            :y="(d: SpendingTrendChartPoint) => d.expenses"
                                            color="url(#spendingTrendFill)"
                                            :opacity="1"
                                        />
                                        <VisLine
                                            :x="(d: SpendingTrendChartPoint) => d.date"
                                            :y="(d: SpendingTrendChartPoint) => d.expenses"
                                            :color="spendingTrendChartConfig.expenses.color"
                                            :line-width="2.5"
                                        />
                                        <VisAxis
                                            type="x"
                                            :x="(d: SpendingTrendChartPoint) => d.date"
                                            :tick-line="false"
                                            :domain-line="false"
                                            :grid-line="false"
                                            :num-ticks="8"
                                            :tick-format="(d: number) => new Date(d).toLocaleDateString('en-US', { day: 'numeric' })"
                                        />
                                        <VisAxis
                                            type="y"
                                            :num-ticks="4"
                                            :tick-line="false"
                                            :domain-line="false"
                                            :tick-format="(value: number) => `PHP ${Math.round(value).toLocaleString()}`"
                                        />
                                        <ChartTooltip />
                                        <ChartCrosshair
                                            :template="componentToString(spendingTrendChartConfig, ChartTooltipContent, {
                                                indicator: 'line',
                                                labelFormatter: (value: number | Date) =>
                                                    new Date(value).toLocaleDateString('en-US', {
                                                        month: 'short',
                                                        day: 'numeric',
                                                    }),
                                            })"
                                            :color="spendingTrendChartConfig.expenses.color"
                                        />
                                    </VisXYContainer>

                                    <ChartLegendContent class="pt-4" />
                                </ChartContainer>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                Days with no expenses remain at zero so the full monthly trend is easier to read.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Budget Comparison</CardTitle>
                        <CardDescription>Budgeted versus actual spending for the selected month</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="breakdowns.budget_vs_actual.length === 0"
                            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                        >
                            No budgets found for the selected month.
                        </div>
                        <div v-else class="rounded-2xl border bg-background p-4">
                            <BarChart :labels="budgetComparisonLabels" :series="budgetComparisonSeries" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <CardTitle>Budget Analysis Table</CardTitle>
                        <CardDescription>Detailed category-level variance for {{ monthTitle }}</CardDescription>
                    </div>
                    <Badge variant="outline" class="w-fit rounded-full px-3 py-1 text-[11px]">
                        {{ breakdowns.budget_vs_actual.length }} categories
                    </Badge>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="breakdowns.budget_vs_actual.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No budgets found for the selected month.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
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
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
