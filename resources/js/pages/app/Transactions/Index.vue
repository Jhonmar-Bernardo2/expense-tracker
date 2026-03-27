<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Pencil,
    Plus,
    Receipt,
    RotateCcw,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { displayDepartmentName } from '@/lib/plain-language';
import { dashboard } from '@/routes/app';
import { store as storeApprovalVoucher } from '@/routes/app/approval-vouchers';
import { index } from '@/routes/app/transactions';
import type {
    User,
    BreadcrumbItem,
    Category,
    CategoryType,
    DepartmentOption,
    DepartmentScope,
    Paginator,
    Transaction,
} from '@/types';

type TransactionTypeTab = CategoryType | 'all';
type TransactionTypeOption = { value: CategoryType; label: string };

const props = defineProps<{
    transactions: Paginator<Transaction>;
    categories: Category[];
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    filters: {
        type: CategoryType | null;
        category: number | null;
        month: number | null;
        year: number | null;
        search: string | null;
        department: number | null;
    };
    types: TransactionTypeOption[];
    years: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Transactions', href: index() },
];

const page = usePage();

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

const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const editingTransaction = ref<Transaction | null>(null);
const deletingTransaction = ref<Transaction | null>(null);
const selectedType = ref<TransactionTypeTab>(props.filters.type ?? 'all');
const selectedCategory = ref<number | 'all'>(props.filters.category ?? 'all');
const selectedMonth = ref<number | 'all'>(props.filters.month ?? 'all');
const selectedYear = ref<number | 'all'>(props.filters.year ?? 'all');
const selectedDepartment = ref<number | 'all'>(
    props.filters.department ?? 'all',
);
const search = ref(props.filters.search ?? '');

const canSelectDepartment = computed(
    () => props.department_scope.can_select_department,
);
const currentUser = computed(() => page.props.auth.user as User | null);
const isFinancialManagementUser = computed(
    () => currentUser.value?.department?.is_financial_management === true,
);
const canRequestTransaction = computed(
    () => currentUser.value?.role === 'staff',
);
const defaultDepartmentId = computed(
    () =>
        props.department_scope.department_id ??
        currentUser.value?.department?.id ??
        props.departments[0]?.id ??
        null,
);
const transactionPageDescription = computed(() =>
    isFinancialManagementUser.value
        ? 'Final income and expense records across all departments. Financial Management entries are applied immediately.'
        : 'Final income and expense records. New requests are reviewed by the Finance Team before they appear here.',
);
const transactionDialogTitle = computed(() =>
    editingTransaction.value
        ? isFinancialManagementUser.value
            ? 'Update transaction'
            : 'Update request'
        : isFinancialManagementUser.value
          ? 'New transaction'
          : 'New request',
);
const transactionDialogDescription = computed(() =>
    isFinancialManagementUser.value
        ? 'Fill in the details, then save a draft or apply the transaction immediately.'
        : 'Fill in the details, then send your request to the Finance Team for review.',
);
const transactionActionSectionTitle = computed(() =>
    isFinancialManagementUser.value ? 'Action' : 'Approval Action',
);
const transactionActionSectionDescription = computed(() =>
    isFinancialManagementUser.value
        ? 'Save a draft to continue later or apply this transaction right away.'
        : 'Save a draft to continue later or submit this request for review.',
);
const transactionPrimaryButtonLabel = computed(() =>
    isFinancialManagementUser.value ? 'Apply now' : 'Submit request',
);
const transactionCreateButtonLabel = computed(() =>
    isFinancialManagementUser.value ? 'New transaction' : 'New request',
);
const transactionEditActionLabel = computed(() =>
    isFinancialManagementUser.value ? 'Edit transaction' : 'Request changes',
);
const transactionRemoveActionLabel = computed(() =>
    isFinancialManagementUser.value ? 'Remove transaction' : 'Request removal',
);
const deleteDialogTitle = computed(() =>
    isFinancialManagementUser.value
        ? 'Remove transaction'
        : 'Request to remove transaction',
);
const deleteDialogDescription = computed(() =>
    isFinancialManagementUser.value
        ? 'Add a short reason, then remove this transaction immediately.'
        : 'Add a short reason, then send this removal request to the Finance Team for review.',
);
const deletePrimaryButtonLabel = computed(() =>
    isFinancialManagementUser.value
        ? 'Remove transaction'
        : 'Send removal request',
);

const departmentLabel = computed(() =>
    props.department_scope.is_all_departments
        ? 'All departments'
        : displayDepartmentName(props.department_scope.selected_department),
);

const form = useForm({
    department_id: defaultDepartmentId.value,
    type: (props.filters.type ?? 'expense') as CategoryType,
    category_id: null as number | null,
    title: '',
    amount: '',
    description: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    remarks: '',
});

const deleteForm = useForm({
    department_id: null as number | null,
    target_id: null as number | null,
    remarks: '',
});

const filterCategories = computed(() =>
    selectedType.value === 'all'
        ? props.categories
        : props.categories.filter(
              (category) => category.type === selectedType.value,
          ),
);
const normalizedSearch = computed(() => search.value.trim());
const selectedDepartmentFilterLabel = computed(() => {
    if (!canSelectDepartment.value || props.filters.department === null) {
        return null;
    }

    const department = props.departments.find(
        (option) => option.id === props.filters.department,
    );

    return department
        ? displayDepartmentName(department, department.name)
        : null;
});
const currentScopeLabel = computed(() =>
    canSelectDepartment.value
        ? (selectedDepartmentFilterLabel.value ?? 'All departments')
        : departmentLabel.value,
);
const selectedTypeFilterLabel = computed(() => {
    if (props.filters.type === null) {
        return null;
    }

    return (
        props.types.find((type) => type.value === props.filters.type)?.label ??
        null
    );
});
const selectedCategoryFilterLabel = computed(() => {
    if (props.filters.category === null) {
        return null;
    }

    return (
        props.categories.find(
            (category) => category.id === props.filters.category,
        )?.name ?? null
    );
});
const selectedPeriodFilterLabel = computed(() => {
    const monthLabel =
        props.filters.month === null
            ? null
            : months.find((month) => month.value === props.filters.month)
                  ?.label ?? null;
    const yearLabel =
        props.filters.year === null ? null : String(props.filters.year);

    if (monthLabel === null && yearLabel === null) {
        return null;
    }

    if (monthLabel && yearLabel) {
        return `${monthLabel} ${yearLabel}`;
    }

    return monthLabel ?? yearLabel;
});
const activeFilterBadges = computed(() => {
    const badges: string[] = [];

    if (selectedDepartmentFilterLabel.value) {
        badges.push(`Department: ${selectedDepartmentFilterLabel.value}`);
    }

    if (selectedTypeFilterLabel.value) {
        badges.push(`Type: ${selectedTypeFilterLabel.value}`);
    }

    if (selectedCategoryFilterLabel.value) {
        badges.push(`Category: ${selectedCategoryFilterLabel.value}`);
    }

    if (selectedPeriodFilterLabel.value) {
        badges.push(`Period: ${selectedPeriodFilterLabel.value}`);
    }

    if ((props.filters.search ?? '').trim()) {
        badges.push(`Search: ${(props.filters.search ?? '').trim()}`);
    }

    return badges;
});
const hasActiveFilters = computed(() => activeFilterBadges.value.length > 0);
const filtersAreDirty = computed(
    () =>
        selectedType.value !== (props.filters.type ?? 'all') ||
        selectedCategory.value !== (props.filters.category ?? 'all') ||
        selectedMonth.value !== (props.filters.month ?? 'all') ||
        selectedYear.value !== (props.filters.year ?? 'all') ||
        selectedDepartment.value !== (props.filters.department ?? 'all') ||
        normalizedSearch.value !== (props.filters.search ?? '').trim(),
);
const resultsSummary = computed(() => {
    const { from, to, total } = props.transactions.meta;
    const formattedTotal = total.toLocaleString();

    if (from !== null && to !== null) {
        return `Showing ${from}-${to} of ${formattedTotal} transactions`;
    }

    return `${formattedTotal} transactions`;
});

const formCategories = computed(() =>
    props.categories.filter((category) => category.type === form.type),
);

watch(selectedType, () => {
    if (selectedCategory.value === 'all') {
        return;
    }

    if (
        filterCategories.value.some(
            (category) => category.id === selectedCategory.value,
        )
    ) {
        return;
    }

    selectedCategory.value = 'all';
});

watch(
    () => form.type,
    () => {
        if (
            formCategories.value.some(
                (category) => category.id === form.category_id,
            )
        ) {
            return;
        }

        form.category_id = formCategories.value[0]?.id ?? null;
    },
);

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.type = selectedType.value === 'all' ? 'expense' : selectedType.value;
    form.transaction_date = new Date().toISOString().slice(0, 10);
    form.remarks = '';
    form.department_id = defaultDepartmentId.value;

    if (canSelectDepartment.value && selectedDepartment.value !== 'all') {
        form.department_id = selectedDepartment.value;
    }

    form.category_id =
        props.categories.find((category) => category.type === form.type)?.id ??
        null;
};

const openCreateDialog = () => {
    editingTransaction.value = null;
    resetForm();
    isDialogOpen.value = true;
};

const openEditDialog = (transaction: Transaction) => {
    editingTransaction.value = transaction;
    form.department_id = transaction.department_id;
    form.type = transaction.type;
    form.category_id = transaction.category_id;
    form.title = transaction.title;
    form.amount = transaction.amount;
    form.description = transaction.description ?? '';
    form.transaction_date =
        transaction.transaction_date ?? new Date().toISOString().slice(0, 10);
    form.remarks = '';
    form.clearErrors();
    isDialogOpen.value = true;
};

const resetDeleteForm = () => {
    deleteForm.reset();
    deleteForm.clearErrors();
    deleteForm.department_id = null;
    deleteForm.target_id = null;
    deleteForm.remarks = '';
    deletingTransaction.value = null;
};

const openDeleteDialog = (transaction: Transaction) => {
    deletingTransaction.value = transaction;
    deleteForm.department_id = transaction.department_id;
    deleteForm.target_id = transaction.id;
    deleteForm.remarks = '';
    deleteForm.clearErrors();
    isDeleteDialogOpen.value = true;
};

const applyFilters = () => {
    router.get(
        index.url({
            query: {
                type:
                    selectedType.value === 'all'
                        ? undefined
                        : selectedType.value,
                category:
                    selectedCategory.value === 'all'
                        ? undefined
                        : selectedCategory.value,
                month:
                    selectedMonth.value === 'all'
                        ? undefined
                        : selectedMonth.value,
                year:
                    selectedYear.value === 'all'
                        ? undefined
                        : selectedYear.value,
                department:
                    selectedDepartment.value === 'all'
                        ? undefined
                        : selectedDepartment.value,
                search: search.value.trim() || undefined,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    selectedType.value = 'all';
    selectedCategory.value = 'all';
    selectedMonth.value = 'all';
    selectedYear.value = 'all';
    selectedDepartment.value = 'all';
    search.value = '';
    applyFilters();
};

const submit = (autoSubmit: boolean) => {
    form.transform((data) => ({
        ...data,
        module: 'transaction',
        action: editingTransaction.value ? 'update' : 'create',
        target_id: editingTransaction.value?.id ?? null,
        auto_submit: autoSubmit,
    })).post(storeApprovalVoucher().url, {
        preserveScroll: true,
        forceFormData: true,
    });
};

const submitDeleteRequest = () => {
    if (deletingTransaction.value === null) {
        return;
    }

    deleteForm
        .transform((data) => ({
            ...data,
            module: 'transaction',
            action: 'delete',
            target_id: deletingTransaction.value?.id ?? data.target_id,
            department_id:
                deletingTransaction.value?.department_id ?? data.department_id,
            auto_submit: true,
        }))
        .post(storeApprovalVoucher().url, {
            preserveScroll: true,
            forceFormData: true,
        });
};
</script>

<template>
    <Head title="Transactions" />

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
                                <Receipt class="size-4 text-muted-foreground" />
                                Transactions
                            </div>
                            <Badge
                                variant="outline"
                                class="rounded-md font-medium"
                            >
                                {{ currentScopeLabel }}
                            </Badge>
                            <Badge
                                variant="secondary"
                                class="rounded-md font-medium tabular-nums"
                            >
                                {{ transactions.meta.total.toLocaleString() }}
                                total
                            </Badge>
                            <Badge
                                v-if="hasActiveFilters"
                                variant="outline"
                                class="rounded-md font-medium"
                            >
                                {{ activeFilterBadges.length }} filter{{
                                    activeFilterBadges.length === 1 ? '' : 's'
                                }}
                                active
                            </Badge>
                        </div>

                        <p
                            class="max-w-4xl text-sm leading-6 text-muted-foreground"
                        >
                            {{ transactionPageDescription }}
                        </p>

                        <div
                            v-if="hasActiveFilters"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="badge in activeFilterBadges"
                                :key="badge"
                                variant="outline"
                                class="rounded-md border-dashed px-2.5 py-1 text-xs font-normal text-muted-foreground"
                            >
                                {{ badge }}
                            </Badge>
                        </div>
                    </div>

                    <Dialog
                        v-if="canRequestTransaction"
                        v-model:open="isDialogOpen"
                    >
                        <DialogTrigger as-child>
                            <Button
                                class="w-full lg:w-auto"
                                @click="openCreateDialog"
                            >
                                <Plus class="mr-2 size-4" />
                                {{ transactionCreateButtonLabel }}
                            </Button>
                        </DialogTrigger>

                        <DialogContent
                            class="gap-3 border-border/80 bg-background p-4 sm:max-w-4xl sm:p-5"
                        >
                            <DialogHeader>
                                <DialogTitle>{{ transactionDialogTitle }}</DialogTitle>
                                <DialogDescription>
                                    {{ transactionDialogDescription }}
                                </DialogDescription>
                            </DialogHeader>

                            <form
                                class="space-y-4"
                                @submit.prevent="submit(false)"
                            >
                                    <div
                                        class="space-y-4 rounded-2xl border border-border/80 bg-muted/10 p-4"
                                    >
                                        <div class="space-y-1">
                                            <h3 class="text-base font-semibold">
                                                Request details
                                            </h3>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Complete the transaction details
                                                first.
                                            </p>
                                        </div>

                                        <div
                                            v-if="canSelectDepartment"
                                            class="grid gap-2"
                                        >
                                            <Label for="transaction-department"
                                                >Department</Label
                                            >
                                            <Select
                                                v-model="form.department_id"
                                            >
                                                <SelectTrigger
                                                    id="transaction-department"
                                                    class="w-full"
                                                    ><SelectValue
                                                        placeholder="Select a department"
                                                /></SelectTrigger>
                                                <SelectContent>
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
                                            <InputError
                                                :message="
                                                    form.errors.department_id
                                                "
                                            />
                                        </div>

                                        <div
                                            v-else
                                            class="rounded-lg border bg-background px-3 py-2.5 text-sm text-muted-foreground"
                                        >
                                            <span
                                                class="font-medium text-foreground"
                                                >Department:</span
                                            >
                                            {{ departmentLabel }}
                                        </div>

                                        <div
                                            class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)]"
                                        >
                                            <div class="grid gap-2">
                                                <Label for="transaction-type"
                                                    >Type</Label
                                                >
                                                <Select v-model="form.type">
                                                    <SelectTrigger
                                                        id="transaction-type"
                                                        class="w-full"
                                                        ><SelectValue
                                                            placeholder="Select type"
                                                    /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="type in types"
                                                            :key="type.value"
                                                            :value="type.value"
                                                        >
                                                            {{ type.label }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    :message="form.errors.type"
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label
                                                    for="transaction-category"
                                                    >Category</Label
                                                >
                                                <Select
                                                    v-model="form.category_id"
                                                >
                                                    <SelectTrigger
                                                        id="transaction-category"
                                                        class="w-full"
                                                        ><SelectValue
                                                            placeholder="Select category"
                                                    /></SelectTrigger>
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
                                                <InputError
                                                    :message="
                                                        form.errors.category_id
                                                    "
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="transaction-date"
                                                    >Date</Label
                                                >
                                                <Input
                                                    id="transaction-date"
                                                    v-model="
                                                        form.transaction_date
                                                    "
                                                    type="date"
                                                />
                                                <InputError
                                                    :message="
                                                        form.errors
                                                            .transaction_date
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="transaction-title"
                                                >Title</Label
                                            >
                                            <Input
                                                id="transaction-title"
                                                v-model="form.title"
                                                type="text"
                                            />
                                            <InputError
                                                :message="form.errors.title"
                                            />
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="grid gap-2">
                                                <Label for="transaction-amount"
                                                    >Amount</Label
                                                >
                                                <Input
                                                    id="transaction-amount"
                                                    v-model="form.amount"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                />
                                                <InputError
                                                    :message="
                                                        form.errors.amount
                                                    "
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label
                                                    for="transaction-description"
                                                    >Description</Label
                                                >
                                                <Input
                                                    id="transaction-description"
                                                    v-model="form.description"
                                                    type="text"
                                                />
                                                <InputError
                                                    :message="
                                                        form.errors.description
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="transaction-remarks"
                                                >Remarks</Label
                                            >
                                            <textarea
                                                id="transaction-remarks"
                                                v-model="form.remarks"
                                                class="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                            <InputError
                                                :message="form.errors.remarks"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="space-y-4 rounded-2xl border border-border/80 bg-muted/10 p-4"
                                    >
                                        <div class="space-y-1">
                                            <h3 class="text-base font-semibold">
                                                {{ transactionActionSectionTitle }}
                                            </h3>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    transactionActionSectionDescription
                                                }}
                                            </p>
                                        </div>

                                        <DialogFooter
                                            class="gap-3 sm:justify-end"
                                        >
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                @click="
                                                    isDialogOpen = false;
                                                    resetForm();
                                                "
                                                >Cancel</Button
                                            >
                                            <Button
                                                type="button"
                                                variant="outline"
                                                :disabled="form.processing"
                                                @click="submit(false)"
                                            >
                                                <Spinner
                                                    v-if="form.processing"
                                                />
                                                Save draft
                                            </Button>
                                            <Button
                                                type="button"
                                                :disabled="form.processing"
                                                @click="submit(true)"
                                            >
                                                <Spinner
                                                    v-if="form.processing"
                                                />
                                                {{
                                                    transactionPrimaryButtonLabel
                                                }}
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                </form>
                            </DialogContent>
                    </Dialog>
                </div>

                <div class="px-4 py-4">
                    <div
                        class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.8fr)_auto]"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="filter-search"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Search
                            </Label>
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="filter-search"
                                    v-model="search"
                                    type="text"
                                    placeholder="Title, description, amount"
                                    class="pl-9"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                        </div>

                        <div
                            v-if="canSelectDepartment"
                            class="grid gap-1.5"
                        >
                            <Label
                                for="filter-department"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Department
                            </Label>
                            <Select v-model="selectedDepartment">
                                <SelectTrigger id="filter-department">
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

                        <div v-else class="grid gap-1.5">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Scope
                            </span>
                            <div
                                class="flex h-9 items-center gap-2 rounded-md border bg-muted/20 px-3 text-sm text-muted-foreground"
                            >
                                <Building2 class="size-4" />
                                <span class="truncate">{{ departmentLabel }}</span>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-type"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Type
                            </Label>
                            <Select v-model="selectedType">
                                <SelectTrigger id="filter-type">
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All types</SelectItem
                                    >
                                    <SelectItem
                                        v-for="type in types"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-category"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Category
                            </Label>
                            <Select v-model="selectedCategory">
                                <SelectTrigger id="filter-category">
                                    <SelectValue
                                        placeholder="All categories"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All categories</SelectItem
                                    >
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

                        <div class="grid gap-1.5">
                            <Label
                                for="filter-month"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Month
                            </Label>
                            <Select v-model="selectedMonth">
                                <SelectTrigger id="filter-month">
                                    <SelectValue placeholder="All months" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All months</SelectItem
                                    >
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
                                for="filter-year"
                                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                Year
                            </Label>
                            <Select v-model="selectedYear">
                                <SelectTrigger id="filter-year">
                                    <SelectValue placeholder="All years" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All years</SelectItem
                                    >
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
                            class="flex items-end gap-2 md:col-span-2 xl:col-span-1 xl:justify-end"
                        >
                            <Button
                                class="flex-1 xl:flex-none"
                                :disabled="!filtersAreDirty"
                                @click="applyFilters"
                            >
                                Apply
                            </Button>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :disabled="!hasActiveFilters && !filtersAreDirty"
                                @click="resetFilters"
                            >
                                <RotateCcw class="size-4" />
                                <span class="sr-only">Reset filters</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <Dialog
                v-model:open="isDeleteDialogOpen"
                @update:open="
                    (open) => {
                        if (!open) {
                            resetDeleteForm();
                        }
                    }
                "
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ deleteDialogTitle }}</DialogTitle>
                        <DialogDescription>
                            {{ deleteDialogDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        class="space-y-4"
                        @submit.prevent="submitDeleteRequest"
                    >
                        <div
                            v-if="deletingTransaction"
                            class="rounded-lg border bg-muted/20 p-4 text-sm"
                        >
                            <div class="font-medium">
                                {{ deletingTransaction.title }}
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                {{
                                    deletingTransaction.category?.name ??
                                    'Uncategorized'
                                }}
                                -
                                {{
                                    deletingTransaction.transaction_date ?? '-'
                                }}
                            </div>
                            <div class="mt-2 text-muted-foreground">
                                Amount:
                                {{
                                    Number(deletingTransaction.amount).toFixed(
                                        2,
                                    )
                                }}
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="delete-transaction-remarks"
                                >Remarks</Label
                            >
                            <textarea
                                id="delete-transaction-remarks"
                                v-model="deleteForm.remarks"
                                class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <InputError :message="deleteForm.errors.remarks" />
                        </div>

                        <DialogFooter class="gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="secondary"
                                @click="isDeleteDialogOpen = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="deleteForm.processing"
                            >
                                <Spinner v-if="deleteForm.processing" />
                                {{ deletePrimaryButtonLabel }}
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
                        <div class="text-sm font-semibold text-foreground">
                            Transaction ledger
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ resultsSummary }}
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge variant="outline" class="rounded-md font-medium">
                            {{ currentScopeLabel }}
                        </Badge>
                        <span>
                            {{
                                hasActiveFilters
                                    ? `${activeFilterBadges.length} active filter${
                                          activeFilterBadges.length === 1
                                              ? ''
                                              : 's'
                                      }`
                                    : 'No active filters'
                            }}
                        </span>
                    </div>
                </div>

                <div class="p-4">
                    <div
                        v-if="transactions.data.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        No transactions found for the current filters.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="transaction in transactions.data"
                                :key="`transaction-card-${transaction.id}`"
                                class="rounded-lg border border-border/70 bg-background p-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            {{ transaction.title }}
                                        </div>
                                        <div
                                            v-if="transaction.description"
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{ transaction.description }}
                                        </div>
                                    </div>
                                    <Badge
                                        :variant="
                                            transaction.type === 'income'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        class="rounded-md px-2 py-0.5 capitalize"
                                    >
                                        {{ transaction.type }}
                                    </Badge>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
                                            Date
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                transaction.transaction_date ??
                                                '-'
                                            }}
                                        </div>
                                    </div>
                                    <div v-if="canSelectDepartment">
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
                                            Department
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                transaction.department
                                                    ? displayDepartmentName(
                                                          transaction.department,
                                                          transaction.department
                                                              .name,
                                                      )
                                                    : '-'
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
                                            Category
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                transaction.category?.name ??
                                                '-'
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                        >
                                            Amount
                                        </div>
                                        <div
                                            class="mt-1 font-medium tabular-nums"
                                            :class="
                                                transaction.type === 'income'
                                                    ? 'text-emerald-600'
                                                    : 'text-foreground'
                                            "
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
                                        </div>
                                    </div>
                                </div>

                                <ResponsiveActionGroup class="mt-3" align="end">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(transaction)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        {{ transactionEditActionLabel }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openDeleteDialog(transaction)"
                                    >
                                        <Trash2 class="mr-2 size-4" />
                                        {{ transactionRemoveActionLabel }}
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div class="hidden md:block">
                            <div
                                class="rounded-lg border border-border/70 bg-background"
                            >
                                <Table>
                                    <TableHeader class="bg-muted/35">
                                        <TableRow class="hover:bg-transparent">
                                            <TableHead
                                                class="h-10 w-[7rem] px-3 text-[11px] font-semibold uppercase tracking-[0.14em] whitespace-nowrap"
                                            >
                                                Date
                                            </TableHead>
                                            <TableHead
                                                v-if="canSelectDepartment"
                                                class="h-10 w-[10rem] px-3 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                            >
                                                Department
                                            </TableHead>
                                            <TableHead
                                                class="h-10 px-3 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                            >
                                                Title
                                            </TableHead>
                                            <TableHead
                                                class="h-10 w-[8rem] px-3 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                            >
                                                Category
                                            </TableHead>
                                            <TableHead
                                                class="h-10 w-[6.5rem] px-3 text-[11px] font-semibold uppercase tracking-[0.14em] whitespace-nowrap"
                                            >
                                                Type
                                            </TableHead>
                                            <TableHead
                                                class="h-10 w-[8rem] px-3 text-right text-[11px] font-semibold uppercase tracking-[0.14em] whitespace-nowrap"
                                            >
                                                Amount
                                            </TableHead>
                                            <TableHead
                                                class="h-10 w-[5rem] px-2 text-right text-[11px] font-semibold uppercase tracking-[0.14em] whitespace-nowrap"
                                            >
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="transaction in transactions.data"
                                            :key="transaction.id"
                                        >
                                            <TableCell
                                                class="w-[7rem] px-3 py-2.5 align-top text-sm text-muted-foreground whitespace-nowrap"
                                            >
                                                {{
                                                    transaction.transaction_date ??
                                                    '-'
                                                }}
                                            </TableCell>
                                            <TableCell
                                                v-if="canSelectDepartment"
                                                class="w-[10rem] px-3 py-2.5 align-top text-sm text-muted-foreground"
                                            >
                                                <div
                                                    class="truncate"
                                                    :title="
                                                        transaction.department
                                                            ? displayDepartmentName(
                                                                  transaction.department,
                                                                  transaction
                                                                      .department
                                                                      .name,
                                                              )
                                                            : '-'
                                                    "
                                                >
                                                    {{
                                                        transaction.department
                                                            ? displayDepartmentName(
                                                                  transaction.department,
                                                                  transaction
                                                                      .department
                                                                      .name,
                                                              )
                                                            : '-'
                                                    }}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                class="px-3 py-2.5 align-top"
                                            >
                                                <div
                                                    class="truncate text-sm font-medium"
                                                    :title="transaction.title"
                                                >
                                                    {{ transaction.title }}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                class="w-[8rem] px-3 py-2.5 align-top text-sm text-muted-foreground"
                                            >
                                                <div
                                                    class="truncate"
                                                    :title="
                                                        transaction.category
                                                            ?.name ?? '-'
                                                    "
                                                >
                                                    {{
                                                        transaction.category?.name ??
                                                        '-'
                                                    }}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                class="w-[6.5rem] px-3 py-2.5 align-top"
                                            >
                                                <Badge
                                                    :variant="
                                                        transaction.type ===
                                                        'income'
                                                            ? 'default'
                                                            : 'secondary'
                                                    "
                                                    class="rounded-md px-2 py-0.5 capitalize"
                                                >
                                                    {{ transaction.type }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell
                                                class="w-[8rem] px-3 py-2.5 text-right align-top text-sm font-medium tabular-nums whitespace-nowrap"
                                                :class="
                                                    transaction.type === 'income'
                                                        ? 'text-emerald-600'
                                                        : 'text-foreground'
                                                "
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
                                            <TableCell
                                                class="w-[5rem] px-2 py-2.5 align-top"
                                            >
                                                <div class="flex justify-end gap-0.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        :title="
                                                            transactionEditActionLabel
                                                        "
                                                        @click="
                                                            openEditDialog(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <Pencil
                                                            class="size-4"
                                                        />
                                                        <span class="sr-only">
                                                            {{
                                                                transactionEditActionLabel
                                                            }}
                                                        </span>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        class="text-muted-foreground hover:text-destructive"
                                                        :title="
                                                            transactionRemoveActionLabel
                                                        "
                                                        @click="
                                                            openDeleteDialog(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-4"
                                                        />
                                                        <span class="sr-only">
                                                            {{
                                                                transactionRemoveActionLabel
                                                            }}
                                                        </span>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="transactions.meta.last_page > 1"
                    class="border-t border-border/70 px-4 py-3"
                >
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem
                                ><PaginationPrev
                                    :href="transactions.links.prev"
                                    :disabled="!transactions.links.prev"
                            /></PaginationItem>
                            <PaginationItem
                                v-for="link in transactions.meta.links"
                                :key="link.label"
                            >
                                <PaginationLink
                                    v-if="
                                        link.label !== 'Previous' &&
                                        link.label !== 'Next'
                                    "
                                    :href="link.url"
                                    :is-active="link.active"
                                    :disabled="!link.url"
                                >
                                    <span v-html="link.label" />
                                </PaginationLink>
                            </PaginationItem>
                            <PaginationItem
                                ><PaginationNext
                                    :href="transactions.links.next"
                                    :disabled="!transactions.links.next"
                            /></PaginationItem>
                        </PaginationContent>
                    </Pagination>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
