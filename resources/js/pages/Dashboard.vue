<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
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
    BreadcrumbItem,
    Budget,
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

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

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

const budgetUsage = computed(() => {
    const totalBudgeted = props.budgets.current_month_summary.total_budgeted;

    if (totalBudgeted <= 0) {
        return 0;
    }

    return Math.min(
        (props.budgets.current_month_summary.total_spent / totalBudgeted) * 100,
        100,
    );
});

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
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <Landmark class="size-5" />
                            Financial Dashboard
                        </CardTitle>
                        <CardDescription>
                            Monitor cash flow and budget health for
                            {{ monthLabel }}.
                        </CardDescription>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div v-if="canSelectDepartment" class="grid gap-2">
                            <Label for="dashboard-department">Department</Label>
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
                </CardHeader>
            </Card>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Total Income</CardTitle>
                        <TrendingUp class="size-4 text-emerald-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(totals.income)
                    }}</CardContent>
                </Card>
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Total Expenses</CardTitle>
                        <TrendingDown class="size-4 text-orange-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(totals.expenses)
                    }}</CardContent>
                </Card>
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Net Balance</CardTitle>
                        <Wallet class="size-4 text-sky-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold">{{
                        formatCurrency(totals.balance)
                    }}</CardContent>
                </Card>
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm">Budget Usage</CardTitle>
                        <PiggyBank class="size-4 text-amber-600" />
                    </CardHeader>
                    <CardContent class="text-2xl font-semibold"
                        >{{ budgetUsage.toFixed(0) }}%</CardContent
                    >
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BarChart3 class="size-4" />
                            Income vs Expenses
                        </CardTitle>
                        <CardDescription
                            >{{ departmentLabel }} in
                            {{ props.current_month.year }}</CardDescription
                        >
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
                        <CardTitle>Expenses by Category</CardTitle>
                        <CardDescription>{{ monthLabel }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PieChart :items="expensesPie" />
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Receipt class="size-4" />
                            Recent Transactions
                        </CardTitle>
                        <CardDescription>{{ departmentLabel }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="recent_transactions.length === 0"
                            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                        >
                            No recent transactions found.
                        </div>

                        <div v-else class="overflow-hidden rounded-lg border">
                            <Table>
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
                                        <TableCell>{{
                                            transaction.title
                                        }}</TableCell>
                                        <TableCell>{{
                                            transaction.category?.name ?? '-'
                                        }}</TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{
                                                transaction.type === 'income'
                                                    ? '+'
                                                    : '-'
                                            }}{{
                                                Number(
                                                    transaction.amount,
                                                ).toFixed(2)
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Current Month Budgets</CardTitle>
                        <CardDescription>{{ monthLabel }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-4 grid gap-3 sm:grid-cols-3">
                            <div
                                class="rounded-lg border bg-muted/20 px-4 py-3"
                            >
                                <div class="text-xs text-muted-foreground">
                                    Budgeted
                                </div>
                                <div class="text-lg font-semibold">
                                    {{
                                        formatCurrency(
                                            budgets.current_month_summary
                                                .total_budgeted,
                                        )
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border bg-muted/20 px-4 py-3"
                            >
                                <div class="text-xs text-muted-foreground">
                                    Spent
                                </div>
                                <div class="text-lg font-semibold">
                                    {{
                                        formatCurrency(
                                            budgets.current_month_summary
                                                .total_spent,
                                        )
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border bg-muted/20 px-4 py-3"
                            >
                                <div class="text-xs text-muted-foreground">
                                    Remaining
                                </div>
                                <div class="text-lg font-semibold">
                                    {{
                                        formatCurrency(
                                            budgets.current_month_summary
                                                .total_remaining,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="budgets.current_month_statuses.length === 0"
                            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                        >
                            No budgets found for this month.
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
                                                budget.amount_remaining.toFixed(
                                                    2,
                                                )
                                            }}</TableCell
                                        >
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
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
