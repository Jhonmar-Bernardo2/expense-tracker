<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Pencil,
    PiggyBank,
    Plus,
    RotateCcw,
    ShieldCheck,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveActionGroup from '@/components/shared/ResponsiveActionGroup.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { displayDepartmentName } from '@/lib/plain-language';
import { dashboard } from '@/routes/app';
import { store as storeApprovalVoucher } from '@/routes/app/approval-vouchers';
import {
    destroy as destroyBudget,
    index,
    store as storeBudget,
    update as updateBudget,
} from '@/routes/finance/budgets';
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
const totalApprovedBudget = computed(
    () => props.allocation_summary.total_approved_budget,
);
const totalAllocatedBudget = computed(
    () => props.allocation_summary.total_allocated_budget,
);
const remainingDepartmentBudget = computed(
    () => props.allocation_summary.remaining_budget,
);
const remainingAfterSpending = computed(
    () => props.allocation_summary.remaining_after_spending,
);
const canAllocateCategoryBudgets = computed(
    () => props.allocation_summary.can_allocate_category_budgets,
);
const categoryAllocationBlockMessage = computed(
    () => props.allocation_summary.allocation_block_message,
);
const canCreateCategoryBudget = computed(
    () => hasApprovedAllocation.value && canAllocateCategoryBudgets.value,
);
const selectedDepartmentLabel = computed(() =>
    displayDepartmentName(
        props.financial_management_department,
        props.financial_management_department.name,
    ),
);
const resolvePeriodLabel = (month: number, year: number) => {
    const monthLabel =
        props.months.find((option) => option.value === month)?.label ?? month;

    return `${monthLabel} ${year}`;
};
const appliedPeriodLabel = computed(() =>
    resolvePeriodLabel(props.filters.month, props.filters.year),
);
const pendingPeriodLabel = computed(() =>
    resolvePeriodLabel(selectedMonth.value, selectedYear.value),
);
const filtersAreDirty = computed(
    () =>
        selectedMonth.value !== props.filters.month ||
        selectedYear.value !== props.filters.year,
);
const budgetListSummary = computed(() => {
    const count = props.budgets.length.toLocaleString();

    return `${count} category budget${
        props.budgets.length === 1 ? '' : 's'
    } for ${appliedPeriodLabel.value}`;
});
const allocationStatusLabel = computed(() =>
    props.active_allocation
        ? 'Approved allocation on file'
        : 'Awaiting monthly approval',
);
const allocationMetrics = computed(() => [
    {
        id: 'budget-approved-allocation',
        label: 'Total approved budget',
        value: formatCurrency(totalApprovedBudget.value),
        helper: 'Approved by Admin for the selected month.',
        icon: PiggyBank,
        tone: 'info' as const,
    },
    {
        id: 'budget-allocated-categories',
        label: 'Total allocated budget',
        value: formatCurrency(totalAllocatedBudget.value),
        helper: 'Already divided across department categories.',
        icon: Wallet,
        tone: 'info' as const,
    },
    {
        id: 'budget-unallocated',
        label: 'Remaining budget',
        value: formatCurrency(remainingDepartmentBudget.value),
        helper: 'Still available for category allocation.',
        icon: ShieldCheck,
        tone:
            remainingDepartmentBudget.value <= 0
                ? ('danger' as const)
                : ('success' as const),
    },
    {
        id: 'budget-spent',
        label: 'Total spent',
        value: formatCurrency(props.allocation_summary.total_spent),
        helper: 'Approved spending for this month.',
        icon: Wallet,
        tone: 'warning' as const,
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
    props.active_allocation
        ? 'Update monthly budget request'
        : 'Request monthly budget',
);

const allocationDialogDescription = computed(() =>
    props.active_allocation
        ? 'Send an updated monthly budget to admin for approval.'
        : 'Send a monthly budget request to admin before category budgets are assigned.',
);

const categoryDialogTitle = computed(() =>
    editingBudget.value ? 'Edit category budget' : 'Add category budget',
);

const categoryDialogDescription = computed(() =>
    editingBudget.value
        ? 'Update this category budget for the selected period.'
        : 'Set part of the approved monthly budget aside for an expense category.',
);
const categoryBudgetLimit = computed(() =>
    Math.max(
        remainingDepartmentBudget.value +
            (editingBudget.value?.amount_limit ?? 0),
        0,
    ),
);
const isCurrentPeriodCategoryForm = computed(
    () =>
        categoryForm.month === selectedMonth.value &&
        categoryForm.year === selectedYear.value,
);
const categoryBudgetSummaryHelper = computed(() => {
    if (!isCurrentPeriodCategoryForm.value) {
        return 'Validation will use the month and year selected in this form.';
    }

    if (editingBudget.value !== null) {
        return `Maximum you can save for this category right now: ${formatCurrency(categoryBudgetLimit.value)}.`;
    }

    return 'Every category allocation is automatically deducted from the remaining department budget.';
});

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

const resetPeriodFilters = () => {
    selectedMonth.value = props.filters.month;
    selectedYear.value = props.filters.year;
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

const metricPanelClasses = (
    tone: 'info' | 'warning' | 'danger' | 'success',
) =>
    ({
        info: 'border-sky-500/20 bg-sky-500/5',
        warning: 'border-amber-500/20 bg-amber-500/5',
        danger: 'border-destructive/20 bg-destructive/5',
        success: 'border-emerald-500/20 bg-emerald-500/5',
    })[tone];
</script>

<template>
    <Head title="Budgets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <section
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 border-b border-border/70 px-4 py-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="min-w-0 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="flex items-center gap-2 text-base font-semibold tracking-tight text-foreground"
                            >
                                <PiggyBank class="size-4 text-muted-foreground" />
                                Monthly budget
                            </div>
                            <Badge variant="outline" class="rounded-md font-medium">
                                {{ selectedDepartmentLabel }}
                            </Badge>
                            <Badge variant="secondary" class="rounded-md font-medium">
                                {{ appliedPeriodLabel }}
                            </Badge>
                            <Badge variant="outline" class="rounded-md font-medium">
                                {{ allocationStatusLabel }}
                            </Badge>
                        </div>

                        <p class="max-w-4xl text-sm leading-6 text-muted-foreground">
                            Admin approves the monthly budget. The Finance Team
                            then assigns category budgets for the approved
                            period.
                        </p>
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
                                                ? 'Update monthly budget request'
                                                : 'Request monthly budget'
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

                                        <div class="grid gap-4 sm:grid-cols-2">
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
                                                            :value="month.value"
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
                                                >Monthly budget</Label
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
                                                >Notes</Label
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
                                                    isAllocationDialogOpen = false
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
                                                Send request
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>

                    </ResponsiveActionGroup>
                </div>

                <div class="space-y-4 px-4 py-4">
                    <div
                        class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(11rem,1fr))]"
                    >
                        <div
                            v-for="metric in allocationMetrics"
                            :key="metric.id"
                            :class="
                                `rounded-lg border p-3 ${metricPanelClasses(metric.tone)}`
                            "
                        >
                            <div class="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                                {{ metric.label }}
                            </div>
                            <div class="mt-2 text-lg font-semibold tabular-nums">
                                {{ metric.value }}
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ metric.helper }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.8fr)_auto]"
                    >
                        <div class="grid gap-1.5">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Scope
                            </span>
                            <div
                                class="flex h-9 items-center gap-2 rounded-md border bg-muted/20 px-3 text-sm text-muted-foreground"
                            >
                                <Building2 class="size-4" />
                                <span class="truncate">{{ selectedDepartmentLabel }}</span>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-budget-month"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Month
                            </Label>
                            <Select v-model="selectedMonth">
                                <SelectTrigger id="filter-budget-month">
                                    <SelectValue placeholder="Select month" />
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

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-budget-year"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Year
                            </Label>
                            <Select v-model="selectedYear">
                                <SelectTrigger id="filter-budget-year">
                                    <SelectValue placeholder="Select year" />
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

                        <div
                            class="flex items-end gap-2 md:justify-end"
                        >
                            <Button
                                class="flex-1 md:flex-none"
                                :disabled="!filtersAreDirty"
                                @click="applyFilters"
                            >
                                Apply
                            </Button>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :disabled="!filtersAreDirty"
                                @click="resetPeriodFilters"
                            >
                                <RotateCcw class="size-4" />
                                <span class="sr-only">Reset period</span>
                            </Button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant="outline" class="rounded-md font-medium">
                            {{ appliedPeriodLabel }}
                        </Badge>
                        <Badge
                            v-if="filtersAreDirty"
                            variant="outline"
                            class="rounded-md font-medium"
                        >
                            Pending: {{ pendingPeriodLabel }}
                        </Badge>
                        <span>
                            Remaining after spending:
                            {{ formatCurrency(remainingAfterSpending) }}
                        </span>
                    </div>

                    <div
                        v-if="!canManageCategoryBudgets"
                        class="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
                    >
                        Admins can view this page, but only the Finance Team can
                        request the monthly budget and edit category budgets.
                    </div>

                    <div
                        v-if="categoryAllocationBlockMessage"
                        class="rounded-lg border px-4 py-3 text-sm"
                        :class="
                            hasApprovedAllocation
                                ? 'border-destructive/40 bg-destructive/5 text-destructive'
                                : 'border-dashed text-muted-foreground'
                        "
                    >
                        {{ categoryAllocationBlockMessage }}
                    </div>
                </div>
            </section>

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

                        <div class="rounded-xl border bg-muted/20 p-4">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        Total approved budget
                                    </div>
                                    <div
                                        class="mt-1 font-medium text-foreground tabular-nums"
                                    >
                                        {{
                                            formatCurrency(totalApprovedBudget)
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        Total allocated budget
                                    </div>
                                    <div
                                        class="mt-1 font-medium text-foreground tabular-nums"
                                    >
                                        {{
                                            formatCurrency(totalAllocatedBudget)
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        Remaining budget
                                    </div>
                                    <div
                                        class="mt-1 font-medium tabular-nums"
                                        :class="
                                            remainingDepartmentBudget <= 0
                                                ? 'text-destructive'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{
                                            formatCurrency(
                                                remainingDepartmentBudget,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">
                                {{ categoryBudgetSummaryHelper }}
                            </p>
                        </div>

                        <div
                            v-if="
                                !editingBudget && categoryAllocationBlockMessage
                            "
                            class="rounded-lg border px-3 py-3 text-sm"
                            :class="
                                hasApprovedAllocation
                                    ? 'border-destructive/40 bg-destructive/5 text-destructive'
                                    : 'border-dashed text-muted-foreground'
                            "
                        >
                            {{ categoryAllocationBlockMessage }}
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
                                <Label for="category-budget-month">Month</Label>
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
                                >Category budget</Label
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
                                :disabled="
                                    categoryForm.processing ||
                                    (!editingBudget && !canCreateCategoryBudget)
                                "
                            >
                                <Spinner v-if="categoryForm.processing" />
                                {{
                                    editingBudget
                                        ? 'Save changes'
                                        : 'Add budget'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <section
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm"
            >
                <div
                    class="flex flex-col gap-2 border-b border-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <Wallet class="size-4" />
                            Budgets by category
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ budgetListSummary }}
                        </div>
                    </div>

                    <Button
                        v-if="canManageCategoryBudgets"
                        :disabled="!canCreateCategoryBudget"
                        @click="openCreateCategoryDialog"
                    >
                        <Plus class="mr-2 size-4" />
                        Add category budget
                    </Button>
                </div>
                <div class="p-4">
                    <div
                        v-if="
                            canManageCategoryBudgets && !hasApprovedAllocation
                        "
                        class="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
                    >
                        Approve a monthly budget first before assigning category
                        budgets.
                    </div>

                    <div
                        v-else-if="
                            canManageCategoryBudgets &&
                            !canAllocateCategoryBudgets
                        "
                        class="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                    >
                        {{ categoryAllocationBlockMessage }}
                    </div>

                    <div
                        v-if="budgets.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        No category budgets found for this period.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="budget in budgets"
                                :key="`budget-card-${budget.id}`"
                                class="rounded-lg border border-border/70 bg-background p-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-foreground">
                                            {{ budget.category_name }}
                                        </div>
                                        <div class="mt-1 text-sm text-muted-foreground">
                                            {{ selectedDepartmentLabel }}
                                        </div>
                                    </div>
                                    <Badge
                                        :variant="budgetStatus(budget).variant"
                                        class="rounded-md px-2 py-0.5"
                                    >
                                        {{ budgetStatus(budget).label }}
                                    </Badge>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
                                            Limit
                                        </div>
                                        <div class="mt-1 font-medium tabular-nums">
                                            {{
                                                formatCurrency(
                                                    budget.amount_limit,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
                                            Spent
                                        </div>
                                        <div class="mt-1 font-medium tabular-nums">
                                            {{
                                                formatCurrency(
                                                    budget.amount_spent,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
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
                                    class="mt-3"
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

                        <div class="hidden md:block">
                            <div
                                class="min-w-[58rem] overflow-hidden rounded-lg border border-border/70"
                            >
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-muted/35 text-left text-muted-foreground">
                                            <tr>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Category
                                                </th>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Limit
                                                </th>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Spent
                                                </th>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Remaining
                                                </th>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Status
                                                </th>
                                                <th
                                                    v-if="canManageCategoryBudgets"
                                                    class="h-11 px-4 text-right text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border bg-background">
                                            <tr
                                                v-for="budget in budgets"
                                                :key="budget.id"
                                                class="transition-colors hover:bg-muted/30"
                                            >
                                                <td class="px-4 py-3 font-medium text-foreground">
                                                    {{ budget.category_name }}
                                                </td>
                                                <td class="px-4 py-3 font-medium tabular-nums">
                                                    {{ formatCurrency(budget.amount_limit) }}
                                                </td>
                                                <td class="px-4 py-3 tabular-nums text-muted-foreground">
                                                    {{ formatCurrency(budget.amount_spent) }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 font-medium tabular-nums"
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
                                                </td>
                                                <td class="px-4 py-3">
                                                    <Badge
                                                        :variant="
                                                            budgetStatus(budget)
                                                                .variant
                                                        "
                                                        class="rounded-md px-2 py-0.5"
                                                    >
                                                        {{
                                                            budgetStatus(budget)
                                                                .label
                                                        }}
                                                    </Badge>
                                                </td>
                                                <td
                                                    v-if="canManageCategoryBudgets"
                                                    class="px-4 py-3"
                                                >
                                                    <div class="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            title="Edit category budget"
                                                            @click="
                                                                openEditCategoryDialog(
                                                                    budget,
                                                                )
                                                            "
                                                        >
                                                            <Pencil class="size-4" />
                                                            <span class="sr-only">
                                                                Edit
                                                            </span>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            class="text-muted-foreground hover:text-destructive"
                                                            title="Remove category budget"
                                                            @click="
                                                                removeCategoryBudget(
                                                                    budget,
                                                                )
                                                            "
                                                        >
                                                            <Trash2 class="size-4" />
                                                            <span class="sr-only">
                                                                Remove
                                                            </span>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
