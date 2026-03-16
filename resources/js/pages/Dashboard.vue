<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { 
    Landmark, 
    TrendingDown, 
    TrendingUp, 
    Wallet, 
    ArrowUpRight, 
    ArrowDownRight,
    AlertCircle,
    CalendarDays
} from 'lucide-vue-next';
import { computed } from 'vue';
import { VisArea, VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import PieChart from '@/components/charts/PieChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import {
    ChartContainer,
    ChartCrosshair,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { ChartConfig } from '@/components/ui/chart';
import type { BreadcrumbItem, Budget, ExpensesByCategoryRow, IncomeVsExpensesRow, Transaction } from '@/types';

const props = defineProps<{
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
        current_month_summary: {
            total_budgeted: number;
            total_spent: number;
            total_remaining: number;
            categories_over_budget: number;
        };
        current_month_statuses: Budget[];
    };
    recent_transactions: Transaction[];
    charts: {
        expenses_by_category: ExpensesByCategoryRow[];
        income_vs_expenses: IncomeVsExpensesRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

// Helper para sa currency formatting
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
};

const monthLabel = computed(() => {
    const date = new Date(props.current_month.year, props.current_month.month - 1, 1);
    return date.toLocaleString(undefined, { month: 'long', year: 'numeric' });
});

const expensesPie = computed(() => {
    return (props.charts.expenses_by_category ?? []).map((row) => ({
        label: row.category_name,
        value: row.total,
    }));
});

type CashFlowPoint = {
    date: Date;
    income: number;
    expenses: number;
};

const cashFlowChartConfig = {
    income: {
        label: 'Income',
        color: 'hsl(160 60% 40%)',
    },
    expenses: {
        label: 'Expenses',
        color: 'hsl(350 65% 54%)',
    },
} satisfies ChartConfig;

const cashFlowChartData = computed<CashFlowPoint[]>(() => {
    return (props.charts.income_vs_expenses ?? []).map((row) => ({
        date: new Date(props.current_month.year, row.month - 1, 1),
        income: row.income,
        expenses: row.expenses,
    }));
});

const cashFlowChartSvgDefs = `
  <linearGradient id="cashFlowIncome" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-income)" stop-opacity="0.35" />
    <stop offset="95%" stop-color="var(--color-income)" stop-opacity="0.04" />
  </linearGradient>
  <linearGradient id="cashFlowExpenses" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-expenses)" stop-opacity="0.28" />
    <stop offset="95%" stop-color="var(--color-expenses)" stop-opacity="0.03" />
  </linearGradient>
`;

const cashFlowMax = computed(() => {
    const values = cashFlowChartData.value.flatMap((item) => [item.income, item.expenses]);
    const max = Math.max(0, ...values);

    return max > 0 ? Math.ceil(max * 1.15) : 100;
});

const budgetUsage = computed(() => {
    const totalBudgeted = props.budgets.current_month_summary.total_budgeted;
    const totalSpent = props.budgets.current_month_summary.total_spent;

    if (totalBudgeted <= 0) {
        return 0;
    }

    return Math.min((totalSpent / totalBudgeted) * 100, 100);
});

const topExpenseCategory = computed(() => {
    const [topCategory] = expensesPie.value ?? [];
    return topCategory;
});

const totalCategorySpend = computed(() => {
    return expensesPie.value.reduce((sum, item) => sum + item.value, 0);
});

const spendingBreakdown = computed(() => {
    const total = totalCategorySpend.value || 1;

    return [...expensesPie.value]
        .sort((a, b) => b.value - a.value)
        .map((item, index) => ({
            ...item,
            rank: index + 1,
            percentage: (item.value / total) * 100,
        }));
});

const spendingChartItems = computed(() => {
    const items = spendingBreakdown.value;

    if (items.length <= 5) {
        return items.map((item) => ({
            label: item.label,
            value: item.value,
        }));
    }

    const primaryItems = items.slice(0, 5).map((item) => ({
        label: item.label,
        value: item.value,
    }));
    const othersValue = items.slice(5).reduce((sum, item) => sum + item.value, 0);

    return [
        ...primaryItems,
        {
            label: 'Others',
            value: othersValue,
        },
    ];
});

const spendingAlerts = computed(() => {
    return (props.budgets.current_month_statuses ?? [])
        .filter((budget) => budget.is_over_budget || budget.percentage_used > 85)
        .sort((a, b) => b.percentage_used - a.percentage_used);
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-8 p-6">
            <section class="rounded-3xl border border-sidebar-border/60 bg-gradient-to-br from-background via-muted/10 to-muted/30 p-6 shadow-sm">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                            <Landmark class="size-3.5" />
                            Financial Dashboard
                        </div>
                        <div>
                            <h1 class="text-3xl font-semibold tracking-tight">Your money, organized at a glance.</h1>
                            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                                Monitor cash flow, category spending, and budget health for {{ monthLabel }} in one clean workspace.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[420px]">
                        <div class="rounded-2xl border bg-background p-4 shadow-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">This Month</div>
                            <div class="mt-2 text-lg font-semibold">{{ monthLabel }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">Current reporting period</div>
                        </div>
                        <div class="rounded-2xl border bg-background p-4 shadow-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Budget Status</div>
                            <div class="mt-2 text-lg font-semibold">{{ budgetUsage.toFixed(0) }}%</div>
                            <div class="mt-1 text-xs text-muted-foreground">Used from total budget</div>
                        </div>
                        <div class="rounded-2xl border bg-background p-4 shadow-sm">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Top Spend</div>
                            <div class="mt-2 text-lg font-semibold">{{ topExpenseCategory?.label ?? 'No data' }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ topExpenseCategory ? formatCurrency(topExpenseCategory.value) : 'No category spending yet' }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm transition-all hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500/55" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Income</CardTitle>
                        <div class="rounded-full bg-emerald-500/10 p-2">
                            <ArrowUpRight class="size-4 text-emerald-700 dark:text-emerald-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight text-foreground">
                            {{ formatCurrency(props.totals.income) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Overall earnings</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm transition-all hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-rose-500/55" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Expenses</CardTitle>
                        <div class="rounded-full bg-rose-500/10 p-2">
                            <ArrowDownRight class="size-4 text-rose-700 dark:text-rose-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight text-foreground">
                            {{ formatCurrency(props.totals.expenses) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Overall spending</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm transition-all hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-sky-500/45" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Net Balance</CardTitle>
                        <div class="rounded-full bg-sky-500/10 p-2">
                            <Wallet class="size-4 text-sky-700 dark:text-sky-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight" :class="props.totals.balance >= 0 ? 'text-foreground' : 'text-rose-700 dark:text-rose-400'">
                            {{ formatCurrency(props.totals.balance) }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Available funds</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border border-sidebar-border/60 bg-card shadow-sm transition-all hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-amber-500/50" />
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Budget Usage</CardTitle>
                        <div class="rounded-full bg-amber-500/10 p-2">
                            <TrendingDown class="size-4 text-amber-700 dark:text-amber-400" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-semibold tracking-tight text-foreground">
                            {{ budgetUsage.toFixed(0) }}%
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Spent from current budget</p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card class="border-sidebar-border/50 shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary" class="rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.18em]">
                                        Cash Flow
                                    </Badge>
                                    <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px]">
                                        {{ monthLabel }}
                                    </Badge>
                                </div>
                                <div>
                                    <CardTitle class="text-lg">Cash Flow Overview</CardTitle>
                                    <CardDescription>Income and expenses trend across recent months</CardDescription>
                                </div>
                            </div>
                            <div class="hidden items-center gap-2 lg:flex">
                                <Button variant="outline" size="sm" class="rounded-full text-xs">
                                    Income
                                </Button>
                                <Button variant="outline" size="sm" class="rounded-full text-xs">
                                    Expenses
                                </Button>
                                <Button variant="secondary" size="sm" class="rounded-full text-xs">
                                    Trend
                                </Button>
                            </div>
                            <div class="hidden sm:flex items-center gap-2 lg:hidden">
                                <CalendarDays class="size-4 text-muted-foreground" />
                                <span class="text-xs font-medium">{{ monthLabel }}</span>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="rounded-xl border bg-muted/10 p-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Monthly In</div>
                                    <div class="mt-2 text-lg font-semibold text-foreground">{{ formatCurrency(props.current_month.income) }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">Total inflow recorded this month</div>
                                </div>
                                <div class="rounded-xl border bg-muted/10 p-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Monthly Out</div>
                                    <div class="mt-2 text-lg font-semibold text-foreground">{{ formatCurrency(props.current_month.expenses) }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">Total outgoing transactions</div>
                                </div>
                                <div class="rounded-xl border bg-muted/10 p-4">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Net This Month</div>
                                    <div class="mt-2 text-lg font-semibold" :class="props.current_month.balance >= 0 ? 'text-foreground' : 'text-rose-700 dark:text-rose-400'">
                                        {{ formatCurrency(props.current_month.balance) }}
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">Current difference between income and expenses</div>
                                </div>
                            </div>

                            <div class="rounded-2xl border bg-background p-4">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold">Monthly Trend</div>
                                        <div class="text-xs text-muted-foreground">Track the movement of income and expenses over time</div>
                                    </div>
                                </div>
                                <div class="h-[320px] w-full">
                                    <ChartContainer :config="cashFlowChartConfig" class="h-full w-full" :cursor="false">
                                        <VisXYContainer
                                            :data="cashFlowChartData"
                                            :svg-defs="cashFlowChartSvgDefs"
                                            :margin="{ top: 16, right: 12, bottom: 12, left: 0 }"
                                            :y-domain="[0, cashFlowMax]"
                                        >
                                            <VisArea
                                                :x="(d: CashFlowPoint) => d.date"
                                                :y="[(d: CashFlowPoint) => d.income, (d: CashFlowPoint) => d.expenses]"
                                                :color="(_d: CashFlowPoint, index: number) => ['url(#cashFlowIncome)', 'url(#cashFlowExpenses)'][index]"
                                                :opacity="1"
                                            />
                                            <VisLine
                                                :x="(d: CashFlowPoint) => d.date"
                                                :y="[(d: CashFlowPoint) => d.income, (d: CashFlowPoint) => d.expenses]"
                                                :color="(_d: CashFlowPoint, index: number) => [cashFlowChartConfig.income.color, cashFlowChartConfig.expenses.color][index]"
                                                :line-width="2"
                                            />
                                            <VisAxis
                                                type="x"
                                                :x="(d: CashFlowPoint) => d.date"
                                                :tick-line="false"
                                                :domain-line="false"
                                                :grid-line="false"
                                                :num-ticks="6"
                                                :tick-format="(d: number) => new Date(d).toLocaleDateString('en-US', { month: 'short' })"
                                            />
                                            <VisAxis
                                                type="y"
                                                :num-ticks="4"
                                                :tick-line="false"
                                                :domain-line="false"
                                                :tick-format="(value: number) => `₱${Math.round(value).toLocaleString()}`"
                                            />
                                            <ChartTooltip />
                                            <ChartCrosshair
                                                :template="componentToString(cashFlowChartConfig, ChartTooltipContent, {
                                                    indicator: 'line',
                                                    labelFormatter: (value: number | Date) =>
                                                        new Date(value).toLocaleDateString('en-US', {
                                                            month: 'short',
                                                            year: 'numeric',
                                                        }),
                                                })"
                                                :color="(_d: CashFlowPoint, index: number) => [cashFlowChartConfig.income.color, cashFlowChartConfig.expenses.color][index % 2]"
                                            />
                                        </VisXYContainer>

                                        <ChartLegendContent class="pt-4" />
                                    </ChartContainer>
                                </div>
                            </div>
                        </CardContent>
                </Card>

                <Card class="border-sidebar-border/50 shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg">Spending Insights</CardTitle>
                        <CardDescription>Where your money is going this month</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-3 md:grid-cols-4">
                            <div class="rounded-xl border bg-muted/10 p-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Top Category</div>
                                <div class="mt-2 text-base font-semibold">{{ topExpenseCategory?.label ?? 'No data yet' }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ topExpenseCategory ? formatCurrency(topExpenseCategory.value) : 'Add expenses to generate category insights.' }}
                                </div>
                            </div>
                            <div class="rounded-xl border bg-muted/10 p-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Tracked Categories</div>
                                <div class="mt-2 text-base font-semibold">{{ spendingBreakdown.length }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">Expense categories included this month</div>
                            </div>
                            <div class="rounded-xl border bg-muted/10 p-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Total Spent</div>
                                <div class="mt-2 text-base font-semibold">{{ formatCurrency(props.budgets.current_month_summary.total_spent) }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">Combined expense activity this month</div>
                            </div>
                            <div class="rounded-xl border bg-muted/10 p-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Attention Needed</div>
                                <div class="mt-2 text-base font-semibold">{{ spendingAlerts.length }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">Categories above 85% budget usage</div>
                            </div>
                        </div>

                        <Tabs default-value="breakdown" class="space-y-6">
                            <TabsList class="grid w-full grid-cols-2 lg:w-[320px]">
                                <TabsTrigger value="breakdown">Breakdown</TabsTrigger>
                                <TabsTrigger value="alerts">Alerts</TabsTrigger>
                            </TabsList>

                            <TabsContent value="breakdown" class="space-y-0">
                                <div class="space-y-6">
                                    <div class="rounded-2xl border bg-background p-4">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold">Category Share</div>
                                                <div class="text-xs text-muted-foreground">A visual split of spending for this month</div>
                                            </div>
                                            <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px]">
                                                {{ spendingChartItems.length }} groups
                                            </Badge>
                                        </div>

                                        <div v-if="spendingChartItems.length === 0" class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                            No category share available yet.
                                        </div>

                                        <div v-else class="rounded-xl border bg-muted/10 p-4">
                                            <PieChart :items="spendingChartItems" />
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border bg-background p-4">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold">Top Categories</div>
                                                <div class="text-xs text-muted-foreground">Ranked by total spending</div>
                                            </div>
                                            <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px]">
                                                {{ spendingBreakdown.length }} categories
                                            </Badge>
                                        </div>

                                        <ScrollArea class="h-[280px] pr-4">
                                            <div class="space-y-3">
                                                <div v-if="spendingBreakdown.length === 0" class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                                    No category spending available yet.
                                                </div>
                                                <div v-for="item in spendingBreakdown" :key="item.label" class="rounded-xl border bg-muted/10 p-4">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                                                Rank {{ item.rank }}
                                                            </div>
                                                            <div class="mt-1 text-sm font-semibold">{{ item.label }}</div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-sm font-semibold">{{ formatCurrency(item.value) }}</div>
                                                            <div class="text-xs text-muted-foreground">{{ item.percentage.toFixed(1) }}% of tracked spend</div>
                                                        </div>
                                                    </div>
                                                    <Progress :value="item.percentage" class="mt-3 h-2" indicator-class="bg-slate-700 dark:bg-slate-300" />
                                                </div>
                                            </div>
                                        </ScrollArea>
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="alerts" class="space-y-0">
                                <div class="rounded-2xl border bg-background p-4">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold">Budget Attention List</div>
                                            <div class="text-xs text-muted-foreground">Categories approaching or exceeding their budget</div>
                                        </div>
                                        <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px]">
                                            {{ spendingAlerts.length }} alerts
                                        </Badge>
                                    </div>

                                    <div v-if="spendingAlerts.length === 0" class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        No high-risk spending categories this month.
                                    </div>

                                    <Accordion v-else type="single" collapsible class="w-full">
                                        <AccordionItem
                                            v-for="budget in spendingAlerts"
                                            :key="budget.id"
                                            :value="`budget-${budget.id}`"
                                            class="border-b"
                                        >
                                            <AccordionTrigger class="text-left">
                                                <div class="flex w-full items-center justify-between gap-4 pr-4">
                                                    <div>
                                                        <div class="text-sm font-semibold">{{ budget.category_name }}</div>
                                                        <div class="text-xs text-muted-foreground">
                                                            {{ budget.is_over_budget ? 'Over budget' : 'Nearing budget limit' }}
                                                        </div>
                                                    </div>
                                                    <Badge :variant="budget.is_over_budget ? 'destructive' : 'secondary'" class="rounded-full px-3 py-1 text-[11px]">
                                                        {{ budget.percentage_used?.toFixed(0) }}%
                                                    </Badge>
                                                </div>
                                            </AccordionTrigger>
                                            <AccordionContent class="space-y-4">
                                                <Progress
                                                    :value="Math.min(budget.percentage_used, 100)"
                                                    class="h-2"
                                                    :indicator-class="budget.is_over_budget ? 'bg-rose-500' : 'bg-amber-500'"
                                                />
                                                <div class="grid gap-3 md:grid-cols-3">
                                                    <div class="rounded-lg border bg-muted/10 p-3">
                                                        <div class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Spent</div>
                                                        <div class="mt-1 text-sm font-semibold">{{ formatCurrency(budget.amount_spent) }}</div>
                                                    </div>
                                                    <div class="rounded-lg border bg-muted/10 p-3">
                                                        <div class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Remaining</div>
                                                        <div class="mt-1 text-sm font-semibold">{{ formatCurrency(Math.abs(budget.amount_remaining)) }}</div>
                                                    </div>
                                                    <div class="rounded-lg border bg-muted/10 p-3">
                                                        <div class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Status</div>
                                                        <div class="mt-1 text-sm font-semibold">
                                                            {{ budget.is_over_budget ? 'Reduce spending' : 'Monitor closely' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    </Accordion>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/50 shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-lg">Recent Transactions</CardTitle>
                            <CardDescription>Your latest financial activity</CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px]">
                                {{ props.recent_transactions.length }} items
                            </Badge>
                            <Badge variant="secondary" class="cursor-pointer rounded-full hover:bg-muted">See All</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.recent_transactions.length === 0" class="flex flex-col items-center justify-center py-10 text-muted-foreground">
                            <p class="text-sm italic">No recent transactions found.</p>
                        </div>
                        <Table v-else>
                            <TableBody>
                                <TableRow v-for="tx in props.recent_transactions" :key="tx.id" class="group transition-colors hover:bg-muted/40">
                                    <TableCell class="w-24 text-xs text-muted-foreground">
                                        {{ tx.transaction_date ?? '--' }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-semibold text-sm">{{ tx.title }}</div>
                                        <div class="text-xs text-muted-foreground">{{ tx.category?.name ?? 'Uncategorized' }}</div>
                                    </TableCell>
                                    <TableCell class="text-right font-bold tabular-nums">
                                        <span :class="tx.type === 'income' ? 'text-emerald-600' : 'text-foreground'">
                                            {{ tx.type === 'income' ? '+' : '-' }} {{ Number(tx.amount).toLocaleString() }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/50 shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg">Budget Tracker</CardTitle>
                        <CardDescription>Progress for this month</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-if="props.budgets.current_month_summary.categories_over_budget > 0" 
                            class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-800 dark:bg-rose-950/30 dark:text-rose-200">
                            <AlertCircle class="size-5 shrink-0" />
                            <span class="text-xs font-bold uppercase leading-tight">
                                Warning: {{ props.budgets.current_month_summary.categories_over_budget }} categories exceeded their limit!
                            </span>
                        </div>

                        <div v-if="props.budgets.current_month_statuses.length === 0" class="py-4 text-center text-sm text-muted-foreground italic">
                            No budget set.
                        </div>

                        <div v-for="budget in props.budgets.current_month_statuses.slice(0, 6)" :key="budget.id" class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-semibold">{{ budget.category_name }}</span>
                                <span class="text-xs font-medium" :class="budget.percentage_used > 100 ? 'text-rose-600' : 'text-muted-foreground'">
                                    {{ budget.percentage_used?.toFixed(0) }}%
                                </span>
                            </div>
                            <Progress 
                                :value="budget.percentage_used" 
                                class="h-2"
                                :indicator-class="budget.is_over_budget ? 'bg-rose-500' : budget.percentage_used > 85 ? 'bg-amber-500' : 'bg-emerald-500'"
                            />
                            <div class="flex justify-between text-[10px] text-muted-foreground uppercase tracking-tighter">
                                <span>Spent {{ formatCurrency(budget.amount_spent) }}</span>
                                <span :class="budget.is_over_budget ? 'text-rose-600 font-bold' : ''">
                                    {{ budget.is_over_budget ? 'Over' : 'Left' }}: {{ formatCurrency(Math.abs(budget.amount_remaining)) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom animations or fixes if needed */
.tabular-nums {
    font-variant-numeric: tabular-nums;
}
</style>
