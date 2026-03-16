<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Receipt, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
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
import { destroy, index, store, update } from '@/routes/transactions';
import type { BreadcrumbItem, Budget, Category, CategoryType, Paginator, Transaction } from '@/types';

type TransactionTypeTab = CategoryType | 'all';

type TransactionTypeOption = {
    value: CategoryType;
    label: string;
};

const props = defineProps<{
    transactions: Paginator<Transaction>;
    categories: Category[];
    budgets: Budget[];
    filters: {
        type: CategoryType | null;
        category: number | null;
        month: number | null;
        year: number | null;
        search: string | null;
    };
    types: TransactionTypeOption[];
    years: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Transactions', href: index() },
];

const today = () => new Date().toISOString().slice(0, 10);

const isDialogOpen = ref(false);
const editingTransaction = ref<Transaction | null>(null);

const selectedTypeFilter = ref<TransactionTypeTab>(props.filters.type ?? 'all');
const selectedCategoryFilter = ref<number | 'all'>(props.filters.category ?? 'all');
const selectedMonthFilter = ref<number | 'all'>(props.filters.month ?? 'all');
const selectedYearFilter = ref<number | 'all'>(props.filters.year ?? 'all');
const searchFilter = ref(props.filters.search ?? '');

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const filterCategories = computed(() => {
    if (selectedTypeFilter.value === 'all') {
        return props.categories;
    }

    return props.categories.filter((c) => c.type === selectedTypeFilter.value);
});

const formCategories = computed(() => {
    return props.categories.filter((c) => c.type === form.type);
});

const budgetMonthYear = computed(() => {
    if (!form.transaction_date) {
        return null;
    }

    const [year, month] = form.transaction_date.split('-').map(Number);

    if (!year || !month) {
        return null;
    }

    return {
        month,
        year,
    };
});

const form = useForm({
    type: (props.filters.type ?? 'expense') as CategoryType,
    category_id: null as number | null,
    title: '',
    amount: '',
    description: '',
    transaction_date: today(),
});

const selectedBudget = computed(() => {
    if (form.type !== 'expense' || form.category_id === null || budgetMonthYear.value === null) {
        return null;
    }

    return (
        props.budgets.find((budget) =>
            budget.category_id === form.category_id
            && budget.month === budgetMonthYear.value?.month
            && budget.year === budgetMonthYear.value?.year,
        ) ?? null
    );
});

const formAmount = computed(() => {
    const amount = Number(form.amount);

    return Number.isFinite(amount) ? amount : 0;
});

const editingBudgetContribution = computed(() => {
    if (editingTransaction.value === null || selectedBudget.value === null) {
        return 0;
    }

    if (editingTransaction.value.type !== 'expense') {
        return 0;
    }

    if (editingTransaction.value.category_id !== selectedBudget.value.category_id) {
        return 0;
    }

    if (!editingTransaction.value.transaction_date) {
        return 0;
    }

    const [year, month] = editingTransaction.value.transaction_date.split('-').map(Number);

    if (year !== selectedBudget.value.year || month !== selectedBudget.value.month) {
        return 0;
    }

    const amount = Number(editingTransaction.value.amount);

    return Number.isFinite(amount) ? amount : 0;
});

const budgetContext = computed(() => {
    if (selectedBudget.value === null) {
        return null;
    }

    const adjustedSpent = Math.max(0, selectedBudget.value.amount_spent - editingBudgetContribution.value);
    const projectedSpent = adjustedSpent + Math.max(0, formAmount.value);

    return {
        ...selectedBudget.value,
        adjusted_spent: Number(adjustedSpent.toFixed(2)),
        projected_spent: Number(projectedSpent.toFixed(2)),
        projected_remaining: Number((selectedBudget.value.amount_limit - projectedSpent).toFixed(2)),
        will_exceed: projectedSpent > selectedBudget.value.amount_limit,
    };
});

const dialogTitle = computed(() =>
    editingTransaction.value ? 'Edit transaction' : 'Add transaction',
);

const dialogDescription = computed(() =>
    editingTransaction.value
        ? 'Update the details for this transaction.'
        : 'Record a new income or expense for this account.',
);

const submitLabel = computed(() =>
    editingTransaction.value ? 'Save changes' : 'Create transaction',
);

const openCreateDialog = () => {
    editingTransaction.value = null;
    form.reset();
    form.clearErrors();

    form.type = selectedTypeFilter.value === 'all' ? 'expense' : selectedTypeFilter.value;
    form.transaction_date = today();

    const firstCategory = props.categories.find((c) => c.type === form.type);
    form.category_id = firstCategory?.id ?? null;

    isDialogOpen.value = true;
};

const openEditDialog = (transaction: Transaction) => {
    editingTransaction.value = transaction;
    form.type = transaction.type;
    form.category_id = transaction.category_id;
    form.title = transaction.title;
    form.amount = transaction.amount;
    form.description = transaction.description ?? '';
    form.transaction_date = transaction.transaction_date ?? today();
    form.clearErrors();
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    editingTransaction.value = null;
    form.reset();
    form.clearErrors();
};

watch(
    () => form.type,
    () => {
        const firstCategory = props.categories.find((c) => c.type === form.type);
        form.category_id = firstCategory?.id ?? null;
    },
);

const submit = () => {
    const action = editingTransaction.value
        ? update(editingTransaction.value.id)
        : store();

    form.submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

const applyFilters = () => {
    router.get(
        index.url({
            query: {
                type: selectedTypeFilter.value === 'all' ? undefined : selectedTypeFilter.value,
                category: selectedCategoryFilter.value === 'all' ? undefined : selectedCategoryFilter.value,
                month: selectedMonthFilter.value === 'all' ? undefined : selectedMonthFilter.value,
                year: selectedYearFilter.value === 'all' ? undefined : selectedYearFilter.value,
                search: searchFilter.value.trim().length > 0 ? searchFilter.value.trim() : undefined,
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

const setTypeFilter = (value: TransactionTypeTab) => {
    selectedTypeFilter.value = value;

    if (value !== 'all') {
        const stillValid = filterCategories.value.some((c) => c.id === selectedCategoryFilter.value);

        if (!stillValid) {
            selectedCategoryFilter.value = 'all';
        }
    }

    applyFilters();
};

const deleteTransaction = (transaction: Transaction) => {
    if (!window.confirm(`Delete the "${transaction.title}" transaction?`)) {
        return;
    }

    const action = destroy(transaction.id);

    router.delete(action.url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Transactions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Receipt class="size-5" />
                                Transactions
                            </CardTitle>
                            <CardDescription>
                                Track income and expenses with user-scoped categories.
                            </CardDescription>
                        </div>

                        <Dialog v-model:open="isDialogOpen">
                            <DialogTrigger as-child>
                                <Button class="w-full sm:w-auto" @click="openCreateDialog">
                                    <Plus class="mr-2 size-4" />
                                    Add transaction
                                </Button>
                            </DialogTrigger>

                            <DialogContent class="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                                    <DialogDescription>{{ dialogDescription }}</DialogDescription>
                                </DialogHeader>

                                <form class="space-y-5" @submit.prevent="submit">
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="transaction-type">Type</Label>
                                            <Select v-model="form.type">
                                                <SelectTrigger id="transaction-type" class="w-full">
                                                    <SelectValue placeholder="Select a type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="type in types" :key="type.value" :value="type.value">
                                                        {{ type.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError :message="form.errors.type" />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="transaction-category">Category</Label>
                                            <Select v-model="form.category_id">
                                                <SelectTrigger id="transaction-category" class="w-full">
                                                    <SelectValue placeholder="Select a category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="category in formCategories"
                                                        :key="category.id"
                                                        :value="category.id"
                                                    >
                                                        {{ category.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError :message="form.errors.category_id" />
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="transaction-title">Title</Label>
                                        <Input
                                            id="transaction-title"
                                            v-model="form.title"
                                            type="text"
                                            placeholder="e.g. Salary, Groceries, Rent"
                                            autofocus
                                        />
                                        <InputError :message="form.errors.title" />
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="transaction-amount">Amount</Label>
                                            <Input
                                                id="transaction-amount"
                                                v-model="form.amount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                inputmode="decimal"
                                                placeholder="0.00"
                                            />
                                            <InputError :message="form.errors.amount" />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="transaction-date">Date</Label>
                                            <Input
                                                id="transaction-date"
                                                v-model="form.transaction_date"
                                                type="date"
                                            />
                                            <InputError :message="form.errors.transaction_date" />
                                        </div>
                                    </div>

                                    <Alert
                                        v-if="budgetContext"
                                        :variant="budgetContext.will_exceed ? 'destructive' : 'default'"
                                    >
                                        <AlertTitle>
                                            {{ budgetContext.will_exceed ? 'Budget warning' : 'Budget status' }}
                                        </AlertTitle>
                                        <AlertDescription>
                                            <p>
                                                {{ budgetContext.category_name }} limit:
                                                {{ budgetContext.amount_limit.toFixed(2) }}
                                            </p>
                                            <p>
                                                Current spent:
                                                {{ budgetContext.adjusted_spent.toFixed(2) }}
                                                - Projected spent:
                                                {{ budgetContext.projected_spent.toFixed(2) }}
                                            </p>
                                            <p>
                                                Remaining after save:
                                                {{ budgetContext.projected_remaining.toFixed(2) }}
                                            </p>
                                        </AlertDescription>
                                    </Alert>

                                    <div class="grid gap-2">
                                        <Label for="transaction-description">Description</Label>
                                        <Input
                                            id="transaction-description"
                                            v-model="form.description"
                                            type="text"
                                            placeholder="Optional notes"
                                        />
                                        <InputError :message="form.errors.description" />
                                    </div>

                                    <DialogFooter class="gap-2 sm:justify-end">
                                        <Button type="button" variant="secondary" @click="closeDialog">
                                            Cancel
                                        </Button>
                                        <Button type="submit" :disabled="form.processing">
                                            <Spinner v-if="form.processing" />
                                            {{ submitLabel }}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>

                    <CardContent>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                :variant="selectedTypeFilter === 'all' ? 'default' : 'outline'"
                                size="sm"
                                @click="setTypeFilter('all')"
                            >
                                All
                            </Button>
                            <Button
                                :variant="selectedTypeFilter === 'income' ? 'default' : 'outline'"
                                size="sm"
                                @click="setTypeFilter('income')"
                            >
                                Income
                            </Button>
                            <Button
                                :variant="selectedTypeFilter === 'expense' ? 'default' : 'outline'"
                                size="sm"
                                @click="setTypeFilter('expense')"
                            >
                                Expenses
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Refine results by category, month, year, or search.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="filter-category">Category</Label>
                            <Select
                                :model-value="selectedCategoryFilter"
                                @update:model-value="selectedCategoryFilter = $event as number | 'all'; applyFilters();"
                            >
                                <SelectTrigger id="filter-category" class="w-full">
                                    <SelectValue placeholder="All categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All categories</SelectItem>
                                    <SelectItem
                                        v-for="category in filterCategories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="filter-month">Month</Label>
                                <Select
                                    :model-value="selectedMonthFilter"
                                    @update:model-value="selectedMonthFilter = $event as number | 'all'; applyFilters();"
                                >
                                    <SelectTrigger id="filter-month" class="w-full">
                                        <SelectValue placeholder="All months" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All months</SelectItem>
                                        <SelectItem v-for="month in months" :key="month.value" :value="month.value">
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="filter-year">Year</Label>
                                <Select
                                    :model-value="selectedYearFilter"
                                    @update:model-value="selectedYearFilter = $event as number | 'all'; applyFilters();"
                                >
                                    <SelectTrigger id="filter-year" class="w-full">
                                        <SelectValue placeholder="All years" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All years</SelectItem>
                                        <SelectItem v-for="year in years" :key="year" :value="year">
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Separator />

                        <div class="grid gap-2">
                            <Label for="filter-search">Search</Label>
                            <Input
                                id="filter-search"
                                v-model="searchFilter"
                                type="text"
                                placeholder="Title, description, or category"
                                @keyup.enter="applyFilters"
                            />
                            <div class="flex justify-end">
                                <Button variant="outline" size="sm" @click="applyFilters">
                                    Apply
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Results</CardTitle>
                    <CardDescription>
                        {{ transactions.meta.total }} transaction{{ transactions.meta.total === 1 ? '' : 's' }} found.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="transactions.data.length === 0" class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                        No transactions found for the current filters.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50">
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead class="text-right">Amount</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="transaction in transactions.data" :key="transaction.id">
                                    <TableCell class="whitespace-nowrap text-muted-foreground">
                                        {{ transaction.transaction_date ?? '-' }}
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        <div class="max-w-[320px] truncate">{{ transaction.title }}</div>
                                        <div v-if="transaction.description" class="max-w-[320px] truncate text-xs text-muted-foreground">
                                            {{ transaction.description }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="truncate">{{ transaction.category?.name ?? '-' }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="transaction.type === 'income' ? 'default' : 'secondary'" class="capitalize">
                                            {{ transaction.type }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        <span :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                                            {{ transaction.type === 'income' ? '+' : '-' }}{{ Number(transaction.amount).toFixed(2) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" @click="openEditDialog(transaction)">
                                                <Pencil class="mr-2 size-4" />
                                                Edit
                                            </Button>
                                            <Button variant="outline" size="sm" @click="deleteTransaction(transaction)">
                                                <Trash2 class="mr-2 size-4" />
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div class="mt-4">
                        <Pagination v-if="transactions.meta.last_page > 1">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrev :href="transactions.links.prev" :disabled="!transactions.links.prev" />
                                </PaginationItem>

                                <PaginationItem v-for="link in transactions.meta.links" :key="link.label">
                                    <PaginationLink
                                        v-if="link.label !== 'Previous' && link.label !== 'Next'"
                                        :href="link.url"
                                        :is-active="link.active"
                                        :disabled="!link.url"
                                    >
                                        <span v-html="link.label" />
                                    </PaginationLink>
                                </PaginationItem>

                                <PaginationItem>
                                    <PaginationNext :href="transactions.links.next" :disabled="!transactions.links.next" />
                                </PaginationItem>
                            </PaginationContent>
                        </Pagination>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
