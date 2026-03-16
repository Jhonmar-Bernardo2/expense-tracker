<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Landmark, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import PieChart from '@/components/charts/PieChart.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
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

const monthlyLabels = computed(() => {
    return (props.charts.income_vs_expenses ?? []).map((row) =>
        new Date(props.current_month.year, row.month - 1, 1).toLocaleString(undefined, { month: 'short' }),
    );
});

const monthlySeries = computed(() => {
    const rows = props.charts.income_vs_expenses ?? [];

    return [
        {
            name: 'Income',
            values: rows.map((row) => row.income),
            color: '#22c55e',
        },
        {
            name: 'Expenses',
            values: rows.map((row) => row.expenses),
            color: '#f97316',
        },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <TrendingUp class="size-4" />
                            Total income
                        </CardTitle>
                        <div class="text-2xl font-semibold tabular-nums text-emerald-600">
                            {{ props.totals.income.toFixed(2) }}
                        </div>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <TrendingDown class="size-4" />
                            Total expenses
                        </CardTitle>
                        <div class="text-2xl font-semibold tabular-nums text-rose-600">
                            {{ props.totals.expenses.toFixed(2) }}
                        </div>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Landmark class="size-4" />
                            Remaining balance
                        </CardTitle>
                        <div
                            class="text-2xl font-semibold tabular-nums"
                            :class="props.totals.balance >= 0 ? 'text-foreground' : 'text-rose-600'"
                        >
                            {{ props.totals.balance.toFixed(2) }}
                        </div>
                    </CardHeader>
                </Card>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="space-y-1.5">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Total budgeted this month
                        </CardTitle>
                        <div class="text-2xl font-semibold tabular-nums text-sky-600">
                            {{ props.budgets.current_month_summary.total_budgeted.toFixed(2) }}
                        </div>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="space-y-1.5">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Budget spending this month
                        </CardTitle>
                        <div class="text-2xl font-semibold tabular-nums text-amber-600">
                            {{ props.budgets.current_month_summary.total_spent.toFixed(2) }}
                        </div>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="space-y-1.5">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Categories over budget
                        </CardTitle>
                        <div class="text-2xl font-semibold tabular-nums">
                            {{ props.budgets.current_month_summary.categories_over_budget }}
                        </div>
                    </CardHeader>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="border-sidebar-border/70 shadow-sm lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Current month summary</CardTitle>
                        <CardDescription>
                            {{ monthLabel }} overview
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="default">Income: {{ props.current_month.income.toFixed(2) }}</Badge>
                            <Badge variant="secondary">Expenses: {{ props.current_month.expenses.toFixed(2) }}</Badge>
                            <Badge :variant="props.current_month.balance >= 0 ? 'outline' : 'destructive'">
                                Balance: {{ props.current_month.balance.toFixed(2) }}
                            </Badge>
                        </div>

                        <Separator />

                        <div class="grid gap-4 md:grid-cols-2">
                            <Card class="border-sidebar-border/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle class="text-base">Expenses by category</CardTitle>
                                    <CardDescription>Current month distribution</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <PieChart :items="expensesPie" />
                                </CardContent>
                            </Card>

                            <Card class="border-sidebar-border/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle class="text-base">Income vs expenses</CardTitle>
                                    <CardDescription>{{ props.current_month.year }} monthly totals</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <BarChart :labels="monthlyLabels" :series="monthlySeries" />
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Budget status</CardTitle>
                        <CardDescription>Current month budget progress</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.budgets.current_month_statuses.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            No budgets set for this month.
                        </div>

                        <div v-else class="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="text-right">Remaining</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="budget in props.budgets.current_month_statuses.slice(0, 5)" :key="budget.id">
                                        <TableCell>
                                            <div class="truncate font-medium">{{ budget.category_name }}</div>
                                            <div class="truncate text-xs text-muted-foreground">
                                                {{ budget.amount_spent.toFixed(2) }} / {{ budget.amount_limit.toFixed(2) }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right tabular-nums">
                                            <Badge :variant="budget.is_over_budget ? 'destructive' : budget.percentage_used >= 80 ? 'secondary' : 'outline'">
                                                {{ budget.amount_remaining.toFixed(2) }}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Recent transactions</CardTitle>
                    <CardDescription>Latest activity for this account</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.recent_transactions.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No transactions yet.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Title</TableHead>
                                    <TableHead class="text-right">Amount</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="tx in props.recent_transactions" :key="tx.id">
                                    <TableCell class="whitespace-nowrap text-xs text-muted-foreground">
                                        {{ tx.transaction_date ?? '--' }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="truncate font-medium">{{ tx.title }}</div>
                                        <div class="truncate text-xs text-muted-foreground">{{ tx.category?.name ?? '--' }}</div>
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        <span :class="tx.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                                            {{ tx.type === 'income' ? '+' : '-' }}{{ Number(tx.amount).toFixed(2) }}
                                        </span>
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
