<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Receipt, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { dashboard } from '@/routes';
import { store as storeApprovalVoucher } from '@/routes/approval-vouchers';
import { index } from '@/routes/transactions';
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
const canRequestTransaction = computed(
    () => currentUser.value?.role === 'staff',
);

const departmentLabel = computed(() =>
    props.department_scope.is_all_departments
        ? 'All departments'
        : displayDepartmentName(props.department_scope.selected_department),
);

const form = useForm({
    department_id:
        props.department_scope.department_id ??
        props.departments[0]?.id ??
        null,
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

const formCategories = computed(() =>
    props.categories.filter((category) => category.type === form.type),
);

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
    form.department_id =
        props.department_scope.department_id ??
        props.departments[0]?.id ??
        null;

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
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Receipt class="size-5" />
                                Transactions
                            </CardTitle>
                            <CardDescription
                                >Final income and expense records. New requests
                                are reviewed by the Finance Team before they
                                appear here.</CardDescription
                            >
                        </div>
                        <Dialog
                            v-if="canRequestTransaction"
                            v-model:open="isDialogOpen"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    class="w-full sm:w-auto"
                                    @click="openCreateDialog"
                                >
                                    <Plus class="mr-2 size-4" />
                                    New request
                                </Button>
                            </DialogTrigger>

                            <DialogContent
                                class="gap-3 border-border/80 bg-background p-4 sm:max-w-4xl sm:p-5"
                            >
                                <DialogHeader>
                                    <DialogTitle>{{
                                        editingTransaction
                                            ? 'Update request'
                                            : 'New request'
                                    }}</DialogTitle>
                                    <DialogDescription>
                                        Fill in the details, then send your
                                        request to the Finance Team for review.
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
                                                Approval Action
                                            </h3>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Save a draft to continue later
                                                or submit this request for
                                                review.
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
                                                Submit request
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription
                            >Filter by department, type, category, period, or
                            search.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="canSelectDepartment" class="grid gap-2">
                            <Label for="filter-department">Department</Label>
                            <Select
                                :model-value="selectedDepartment"
                                @update:model-value="
                                    selectedDepartment = $event as
                                        | number
                                        | 'all';
                                    applyFilters();
                                "
                            >
                                <SelectTrigger id="filter-department"
                                    ><SelectValue placeholder="All departments"
                                /></SelectTrigger>
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

                        <div
                            v-else
                            class="flex items-center gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        >
                            <Building2 class="size-4" />
                            {{ departmentLabel }}
                        </div>

                        <div class="grid gap-2">
                            <Label for="filter-type">Type</Label>
                            <Select
                                :model-value="selectedType"
                                @update:model-value="
                                    selectedType = $event as TransactionTypeTab;
                                    applyFilters();
                                "
                            >
                                <SelectTrigger id="filter-type"
                                    ><SelectValue placeholder="All types"
                                /></SelectTrigger>
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

                        <div class="grid gap-2">
                            <Label for="filter-category">Category</Label>
                            <Select
                                :model-value="selectedCategory"
                                @update:model-value="
                                    selectedCategory = $event as number | 'all';
                                    applyFilters();
                                "
                            >
                                <SelectTrigger id="filter-category"
                                    ><SelectValue placeholder="All categories"
                                /></SelectTrigger>
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

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="filter-month">Month</Label>
                                <Select
                                    :model-value="selectedMonth"
                                    @update:model-value="
                                        selectedMonth = $event as
                                            | number
                                            | 'all';
                                        applyFilters();
                                    "
                                >
                                    <SelectTrigger id="filter-month"
                                        ><SelectValue placeholder="All months"
                                    /></SelectTrigger>
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

                            <div class="grid gap-2">
                                <Label for="filter-year">Year</Label>
                                <Select
                                    :model-value="selectedYear"
                                    @update:model-value="
                                        selectedYear = $event as number | 'all';
                                        applyFilters();
                                    "
                                >
                                    <SelectTrigger id="filter-year"
                                        ><SelectValue placeholder="All years"
                                    /></SelectTrigger>
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
                        </div>

                        <div class="grid gap-2">
                            <Label for="filter-search">Search</Label>
                            <Input
                                id="filter-search"
                                v-model="search"
                                type="text"
                                @keyup.enter="applyFilters"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                @click="applyFilters"
                                >Apply</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

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
                        <DialogTitle>Request to remove transaction</DialogTitle>
                        <DialogDescription>
                            Add a short reason, then send this removal request
                            to the Finance Team for review.
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
                                Send removal request
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Results</CardTitle>
                    <CardDescription
                        >{{ transactions.meta.total }} transaction{{
                            transactions.meta.total === 1 ? '' : 's'
                        }}
                        found.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="transactions.data.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No transactions found for the current filters.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="transaction in transactions.data"
                                :key="`transaction-card-${transaction.id}`"
                                class="rounded-xl border p-4 shadow-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="font-medium text-foreground"
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
                                        class="capitalize"
                                    >
                                        {{ transaction.type }}
                                    </Badge>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <div
                                            class="text-xs text-muted-foreground"
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
                                            class="text-xs text-muted-foreground"
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
                                            class="text-xs text-muted-foreground"
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
                                            class="text-xs text-muted-foreground"
                                        >
                                            Amount
                                        </div>
                                        <div
                                            class="mt-1 font-medium tabular-nums"
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

                                <ResponsiveActionGroup class="mt-4" align="end">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(transaction)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Request changes
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openDeleteDialog(transaction)"
                                    >
                                        <Trash2 class="mr-2 size-4" />
                                        Request removal
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div
                            class="hidden overflow-hidden rounded-lg border md:block"
                        >
                            <Table>
                                <TableHeader class="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead v-if="canSelectDepartment"
                                            >Department</TableHead
                                        >
                                        <TableHead>Title</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead class="text-right"
                                            >Amount</TableHead
                                        >
                                        <TableHead class="text-right"
                                            >Actions</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="transaction in transactions.data"
                                        :key="transaction.id"
                                    >
                                        <TableCell>{{
                                            transaction.transaction_date ?? '-'
                                        }}</TableCell>
                                        <TableCell v-if="canSelectDepartment">{{
                                            transaction.department
                                                ? displayDepartmentName(
                                                      transaction.department,
                                                      transaction.department
                                                          .name,
                                                  )
                                                : '-'
                                        }}</TableCell>
                                        <TableCell>
                                            <div class="font-medium">
                                                {{ transaction.title }}
                                            </div>
                                            <div
                                                v-if="transaction.description"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ transaction.description }}
                                            </div>
                                        </TableCell>
                                        <TableCell>{{
                                            transaction.category?.name ?? '-'
                                        }}</TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="
                                                    transaction.type ===
                                                    'income'
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                                class="capitalize"
                                            >
                                                {{ transaction.type }}
                                            </Badge>
                                        </TableCell>
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
                                        <TableCell>
                                            <ResponsiveActionGroup
                                                align="end"
                                                :full-width-on-mobile="false"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        openEditDialog(
                                                            transaction,
                                                        )
                                                    "
                                                >
                                                    <Pencil
                                                        class="mr-2 size-4"
                                                    />
                                                    Request changes
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        openDeleteDialog(
                                                            transaction,
                                                        )
                                                    "
                                                >
                                                    <Trash2
                                                        class="mr-2 size-4"
                                                    />
                                                    Request removal
                                                </Button>
                                            </ResponsiveActionGroup>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <Pagination v-if="transactions.meta.last_page > 1">
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
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
