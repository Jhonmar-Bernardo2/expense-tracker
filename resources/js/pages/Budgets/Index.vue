<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Pencil,
    PiggyBank,
    Plus,
    ShieldCheck,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { store as storeApprovalVoucher } from '@/routes/approval-vouchers';
import {
    destroy as destroyBudget,
    index,
    store as storeBudget,
    update as updateBudget,
} from '@/routes/budgets';
import type {
    Budget,
    BudgetAccessShared,
    BudgetAllocation,
    BudgetAllocationSummary,
    BreadcrumbItem,
    DepartmentOption,
    DepartmentScope,
} from '@/types';

type BudgetCategoryOption = {
    id: number;
    name: string;
};

type MonthOption = {
    value: number;
    label: string;
};

const props = defineProps<{
    budgets: Budget[];
    active_allocation: BudgetAllocation | null;
    allocation_summary: BudgetAllocationSummary;
    categories: BudgetCategoryOption[];
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    financial_management_department: DepartmentOption;
    filters: {
        month: number;
        year: number;
        department: number | null;
    };
    months: MonthOption[];
    years: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Budgets', href: index() },
];

const page = usePage();
const budgetAccess = computed(
    () => page.props.budget_access as BudgetAccessShared,
);

const selectedMonth = ref(props.filters.month);
const selectedYear = ref(props.filters.year);
const isAllocationDialogOpen = ref(false);
const isCategoryDialogOpen = ref(false);
const editingBudget = ref<Budget | null>(null);

const allocationForm = useForm({
    month: props.filters.month,
    year: props.filters.year,
    amount_limit: props.active_allocation?.amount_limit.toFixed(2) ?? '',
    remarks: '',
});

const categoryForm = useForm({
    category_id: props.categories[0]?.id ?? null,
    month: props.filters.month,
    year: props.filters.year,
    amount_limit: '',
});

const canManageCategoryBudgets = computed(
    () => budgetAccess.value.can_manage_category_budgets,
);
const canRequestAllocations = computed(
    () => budgetAccess.value.can_request_allocations,
);
const hasApprovedAllocation = computed(() => props.active_allocation !== null);
const selectedDepartmentLabel = computed(
    () => props.financial_management_department.name,
);
const allocationMetrics = computed(() => [
    {
        id: 'budget-approved-allocation',
        label: 'Approved allocation',
        value: formatCurrency(props.allocation_summary.approved_allocation),
        helper: 'Approved monthly total from admin.',
        icon: PiggyBank,
        tone: 'info' as const,
    },
    {
        id: 'budget-allocated-categories',
        label: 'Allocated to categories',
        value: formatCurrency(props.allocation_summary.total_allocated),
        helper: 'Amount already assigned to category budgets.',
        icon: Wallet,
        tone: 'info' as const,
    },
    {
        id: 'budget-unallocated',
        label: 'Unallocated',
        value: formatCurrency(props.allocation_summary.total_unallocated),
        helper: 'Still available to assign this period.',
        icon: ShieldCheck,
        tone: 'warning' as const,
    },
    {
        id: 'budget-spent',
        label: 'Spent organization-wide',
        value: formatCurrency(props.allocation_summary.total_spent),
        helper: 'Approved expense transactions counted centrally.',
        icon: Wallet,
        tone: 'warning' as const,
    },
    {
        id: 'budget-remaining',
        label: 'Remaining after spending',
        value: formatCurrency(props.allocation_summary.total_remaining),
        helper: 'Approved allocation minus actual spending.',
        icon: Wallet,
        tone:
            props.allocation_summary.total_remaining < 0
                ? ('danger' as const)
                : ('success' as const),
    },
]);

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

const budgetStatus = (budget: Budget) => {
    if (budget.is_over_budget) {
        return { label: 'Over budget', variant: 'destructive' as const };
    }

    if (budget.percentage_used >= 80) {
        return { label: 'Near limit', variant: 'secondary' as const };
    }

    return { label: 'On track', variant: 'outline' as const };
};

const allocationDialogTitle = computed(() =>
    props.active_allocation ? 'Request allocation update' : 'Request allocation',
);

const allocationDialogDescription = computed(() =>
    props.active_allocation
        ? 'Send an updated monthly total allocation to admin for approval.'
        : 'Send a monthly total allocation request to admin before category budgets are assigned.',
);

const categoryDialogTitle = computed(() =>
    editingBudget.value ? 'Edit category budget' : 'Add category budget',
);

const categoryDialogDescription = computed(() =>
    editingBudget.value
        ? 'Update the approved category allocation for this period.'
        : 'Assign part of the approved monthly allocation to an expense category.',
);

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

const resetAllocationForm = () => {
    allocationForm.reset();
    allocationForm.clearErrors();
    allocationForm.month = selectedMonth.value;
    allocationForm.year = selectedYear.value;
    allocationForm.amount_limit =
        props.active_allocation?.amount_limit.toFixed(2) ?? '';
    allocationForm.remarks = '';
};

const openAllocationDialog = () => {
    resetAllocationForm();
    isAllocationDialogOpen.value = true;
};

const submitAllocationRequest = () => {
    allocationForm
        .transform((data) => ({
            ...data,
            module: 'allocation',
            action: props.active_allocation ? 'update' : 'create',
            target_id: props.active_allocation?.id ?? null,
            auto_submit: true,
        }))
        .post(storeApprovalVoucher().url, {
            preserveScroll: true,
        });
};

const requestAllocationDelete = () => {
    if (props.active_allocation === null) {
        return;
    }

    router.post(
        storeApprovalVoucher().url,
        {
            module: 'allocation',
            action: 'delete',
            target_id: props.active_allocation.id,
            auto_submit: true,
        },
        {
            preserveScroll: true,
        },
    );
};

const resetCategoryForm = () => {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.category_id = props.categories[0]?.id ?? null;
    categoryForm.month = selectedMonth.value;
    categoryForm.year = selectedYear.value;
    categoryForm.amount_limit = '';
};

const openCreateCategoryDialog = () => {
    editingBudget.value = null;
    resetCategoryForm();
    isCategoryDialogOpen.value = true;
};

const openEditCategoryDialog = (budget: Budget) => {
    editingBudget.value = budget;
    categoryForm.category_id = budget.category_id;
    categoryForm.month = budget.month;
    categoryForm.year = budget.year;
    categoryForm.amount_limit = budget.amount_limit.toFixed(2);
    categoryForm.clearErrors();
    isCategoryDialogOpen.value = true;
};

const closeCategoryDialog = () => {
    isCategoryDialogOpen.value = false;
    editingBudget.value = null;
    resetCategoryForm();
};

const submitCategoryBudget = () => {
    if (editingBudget.value === null) {
        categoryForm.post(storeBudget().url, {
            preserveScroll: true,
            onSuccess: () => closeCategoryDialog(),
        });

        return;
    }

    categoryForm.put(updateBudget(editingBudget.value.id).url, {
        preserveScroll: true,
        onSuccess: () => closeCategoryDialog(),
    });
};

const removeCategoryBudget = (budget: Budget) => {
    if (
        !window.confirm(
            `Remove the ${budget.category_name} category budget for this period?`,
        )
    ) {
        return;
    }

    router.delete(destroyBudget(budget.id).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Budgets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <PiggyBank class="size-5" />
                                Central Budget Workspace
                            </CardTitle>
                            <CardDescription>
                                Admin approves the full monthly allocation.
                                Financial Management then assigns category
                                budgets for food, utilities, and other expense
                                categories.
                            </CardDescription>
                        </div>

                        <ResponsiveActionGroup align="end">
                            <Dialog
                                v-if="canRequestAllocations"
                                v-model:open="isAllocationDialogOpen"
                            >
                                <DialogTrigger as-child>
                                    <Button
                                        class="w-full sm:w-auto"
                                        @click="openAllocationDialog"
                                    >
                                        <Plus class="mr-2 size-4" />
                                        {{
                                            active_allocation
                                                ? 'Request allocation update'
                                                : 'Request allocation'
                                        }}
                                    </Button>
                                </DialogTrigger>

                                <DialogContent class="sm:max-w-lg">
                                    <DialogHeader>
                                        <DialogTitle>{{
                                            allocationDialogTitle
                                        }}</DialogTitle>
                                        <DialogDescription>
                                            {{ allocationDialogDescription }}
                                        </DialogDescription>
                                    </DialogHeader>

                                    <form
                                        class="space-y-4"
                                        @submit.prevent="
                                            submitAllocationRequest
                                        "
                                    >
                                        <div class="grid gap-2">
                                            <Label>Department</Label>
                                            <div
                                                class="rounded-lg border bg-muted/20 px-3 py-2 text-sm text-muted-foreground"
                                            >
                                                {{ selectedDepartmentLabel }}
                                            </div>
                                        </div>

                                        <div
                                            class="grid gap-4 sm:grid-cols-2"
                                        >
                                            <div class="grid gap-2">
                                                <Label for="allocation-month"
                                                    >Month</Label
                                                >
                                                <Select
                                                    v-model="
                                                        allocationForm.month
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="allocation-month"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select month"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="month in months"
                                                            :key="month.value"
                                                            :value="
                                                                month.value
                                                            "
                                                        >
                                                            {{ month.label }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    :message="
                                                        allocationForm.errors
                                                            .month
                                                    "
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="allocation-year"
                                                    >Year</Label
                                                >
                                                <Select
                                                    v-model="
                                                        allocationForm.year
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="allocation-year"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select year"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="year in years"
                                                            :key="year"
                                                            :value="year"
                                                        >
                                                            {{ year }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    :message="
                                                        allocationForm.errors
                                                            .year
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="allocation-amount"
                                                >Total monthly allocation</Label
                                            >
                                            <Input
                                                id="allocation-amount"
                                                v-model="
                                                    allocationForm.amount_limit
                                                "
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                inputmode="decimal"
                                                placeholder="0.00"
                                                required
                                            />
                                            <InputError
                                                :message="
                                                    allocationForm.errors
                                                        .amount_limit
                                                "
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="allocation-remarks"
                                                >Remarks</Label
                                            >
                                            <textarea
                                                id="allocation-remarks"
                                                v-model="allocationForm.remarks"
                                                class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                            <InputError
                                                :message="
                                                    allocationForm.errors
                                                        .remarks
                                                "
                                            />
                                        </div>

                                        <DialogFooter class="gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                @click="
                                                    isAllocationDialogOpen =
                                                        false
                                                "
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                :disabled="
                                                    allocationForm.processing
                                                "
                                            >
                                                <Spinner
                                                    v-if="
                                                        allocationForm.processing
                                                    "
                                                />
                                                Send to admin
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>

                            <Button
                                v-if="
                                    canRequestAllocations && active_allocation
                                "
                                variant="outline"
                                @click="requestAllocationDelete"
                            >
                                <Trash2 class="mr-2 size-4" />
                                Request allocation delete
                            </Button>
                        </ResponsiveActionGroup>
                    </CardHeader>

                    <CardContent>
                        <DashboardMetricGrid>
                            <DashboardMetricCard
                                v-for="metric in allocationMetrics"
                                :key="metric.id"
                                :label="metric.label"
                                :value="metric.value"
                                :helper="metric.helper"
                                :icon="metric.icon"
                                :tone="metric.tone"
                            />
                        </DashboardMetricGrid>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Period</CardTitle>
                        <CardDescription>
                            Review one central monthly finance period at a time.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        >
                            <Building2 class="size-4" />
                            {{ selectedDepartmentLabel }}
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="filter-budget-month">Month</Label>
                                <Select
                                    :model-value="selectedMonth"
                                    @update:model-value="
                                        selectedMonth = $event as number;
                                        applyFilters();
                                    "
                                >
                                    <SelectTrigger id="filter-budget-month">
                                        <SelectValue
                                            placeholder="Select month"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in months"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="filter-budget-year">Year</Label>
                                <Select
                                    :model-value="selectedYear"
                                    @update:model-value="
                                        selectedYear = $event as number;
                                        applyFilters();
                                    "
                                >
                                    <SelectTrigger id="filter-budget-year">
                                        <SelectValue
                                            placeholder="Select year"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in years"
                                            :key="year"
                                            :value="year"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 text-sm">
                            <div
                                class="flex items-center gap-2 font-medium text-foreground"
                            >
                                <ShieldCheck class="size-4" />
                                Allocation status
                            </div>
                            <p class="mt-2 text-muted-foreground">
                                {{
                                    active_allocation
                                        ? `Approved total allocation for this period: ${formatCurrency(active_allocation.amount_limit)}`
                                        : 'No approved total allocation yet for this period.'
                                }}
                            </p>
                        </div>

                        <div
                            v-if="!canManageCategoryBudgets"
                            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            This page is read-only for admins. Only Financial
                            Management can request allocations and edit
                            category budgets.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                v-if="canManageCategoryBudgets"
                v-model:open="isCategoryDialogOpen"
                @update:open="
                    (open) => {
                        if (!open) {
                            closeCategoryDialog();
                        }
                    }
                "
            >
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{ categoryDialogTitle }}</DialogTitle>
                        <DialogDescription>
                            {{ categoryDialogDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        class="space-y-4"
                        @submit.prevent="submitCategoryBudget"
                    >
                        <div
                            class="rounded-lg border bg-muted/20 px-3 py-2 text-sm text-muted-foreground"
                        >
                            Department: {{ selectedDepartmentLabel }}
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="category-budget-category"
                                    >Category</Label
                                >
                                <Select v-model="categoryForm.category_id">
                                    <SelectTrigger
                                        id="category-budget-category"
                                    >
                                        <SelectValue
                                            placeholder="Select category"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="categoryForm.errors.category_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="category-budget-month"
                                    >Month</Label
                                >
                                <Select v-model="categoryForm.month">
                                    <SelectTrigger id="category-budget-month">
                                        <SelectValue
                                            placeholder="Select month"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in months"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="categoryForm.errors.month"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="category-budget-year">Year</Label>
                                <Select v-model="categoryForm.year">
                                    <SelectTrigger id="category-budget-year">
                                        <SelectValue
                                            placeholder="Select year"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in years"
                                            :key="year"
                                            :value="year"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="categoryForm.errors.year"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="category-budget-amount"
                                >Category allocation</Label
                            >
                            <Input
                                id="category-budget-amount"
                                v-model="categoryForm.amount_limit"
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="0.00"
                                required
                            />
                            <InputError
                                :message="categoryForm.errors.amount_limit"
                            />
                        </div>

                        <DialogFooter class="gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                @click="closeCategoryDialog"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                :disabled="categoryForm.processing"
                            >
                                <Spinner v-if="categoryForm.processing" />
                                {{
                                    editingBudget
                                        ? 'Save changes'
                                        : 'Create budget'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2">
                            <Wallet class="size-4" />
                            Category Budgets
                        </CardTitle>
                        <CardDescription>
                            {{
                                budgets.length === 0
                                    ? 'No category budgets assigned for this period yet.'
                                    : `${budgets.length} category budget${budgets.length === 1 ? '' : 's'} assigned for this period.`
                            }}
                        </CardDescription>
                    </div>

                    <Button
                        v-if="canManageCategoryBudgets"
                        :disabled="!hasApprovedAllocation"
                        @click="openCreateCategoryDialog"
                    >
                        <Plus class="mr-2 size-4" />
                        Add category budget
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="canManageCategoryBudgets && !hasApprovedAllocation"
                        class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        Approve a monthly total allocation first before
                        assigning category budgets.
                    </div>

                    <div
                        v-if="budgets.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No category budgets found for the current filters.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="budget in budgets"
                                :key="`budget-card-${budget.id}`"
                                class="rounded-xl border p-4 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium text-foreground">
                                            {{ budget.category_name }}
                                        </div>
                                        <div class="mt-1 text-sm text-muted-foreground">
                                            {{ selectedDepartmentLabel }}
                                        </div>
                                    </div>
                                    <Badge :variant="budgetStatus(budget).variant">
                                        {{ budgetStatus(budget).label }}
                                    </Badge>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <div class="text-xs text-muted-foreground">
                                            Limit
                                        </div>
                                        <div class="mt-1 font-medium tabular-nums">
                                            {{ formatCurrency(budget.amount_limit) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-muted-foreground">
                                            Spent
                                        </div>
                                        <div class="mt-1 font-medium tabular-nums">
                                            {{ formatCurrency(budget.amount_spent) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-muted-foreground">
                                            Remaining
                                        </div>
                                        <div
                                            class="mt-1 font-medium tabular-nums"
                                            :class="
                                                budget.amount_remaining < 0
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{
                                                formatCurrency(
                                                    budget.amount_remaining,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <ResponsiveActionGroup
                                    v-if="canManageCategoryBudgets"
                                    class="mt-4"
                                    align="end"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditCategoryDialog(budget)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="removeCategoryBudget(budget)"
                                    >
                                        <Trash2 class="mr-2 size-4" />
                                        Remove
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div class="hidden overflow-hidden rounded-lg border md:block">
                            <div class="overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-border text-sm"
                            >
                                <thead
                                    class="bg-muted/50 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            Category
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Limit
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Spent
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Remaining
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th
                                            v-if="canManageCategoryBudgets"
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-border bg-background"
                                >
                                    <tr
                                        v-for="budget in budgets"
                                        :key="budget.id"
                                    >
                                        <td
                                            class="px-4 py-3 font-medium text-foreground"
                                        >
                                            {{ budget.category_name }}
                                        </td>
                                        <td class="px-4 py-3 tabular-nums">
                                            {{ budget.amount_limit.toFixed(2) }}
                                        </td>
                                        <td class="px-4 py-3 tabular-nums">
                                            {{ budget.amount_spent.toFixed(2) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 tabular-nums"
                                            :class="
                                                budget.amount_remaining < 0
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{
                                                budget.amount_remaining.toFixed(
                                                    2,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    budgetStatus(budget).variant
                                                "
                                            >
                                                {{ budgetStatus(budget).label }}
                                            </Badge>
                                        </td>
                                        <td
                                            v-if="canManageCategoryBudgets"
                                            class="px-4 py-3"
                                        >
                                            <ResponsiveActionGroup
                                                align="end"
                                                :full-width-on-mobile="false"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        openEditCategoryDialog(
                                                            budget,
                                                        )
                                                    "
                                                >
                                                    <Pencil
                                                        class="mr-2 size-4"
                                                    />
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        removeCategoryBudget(
                                                            budget,
                                                        )
                                                    "
                                                >
                                                    <Trash2
                                                        class="mr-2 size-4"
                                                    />
                                                    Remove
                                                </Button>
                                            </ResponsiveActionGroup>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
