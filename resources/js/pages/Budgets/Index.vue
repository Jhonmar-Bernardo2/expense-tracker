<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Download,
    FileUp,
    Paperclip,
    Pencil,
    PiggyBank,
    Plus,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { formatFileSize, PDF_ONLY_ACCEPT, SUPPORTING_DOCUMENT_ACCEPT } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as approvalMemoIndex } from '@/routes/approval-memos';
import { store as storeApprovalVoucher } from '@/routes/approval-vouchers';
import { index } from '@/routes/budgets';
import type {
    ApprovalMemoOption,
    BreadcrumbItem,
    Budget,
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
    categories: BudgetCategoryOption[];
    departments: DepartmentOption[];
    department_scope: DepartmentScope;
    filters: {
        month: number;
        year: number;
        department: number | null;
    };
    months: MonthOption[];
    years: number[];
    available_approval_memos: ApprovalMemoOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Budgets', href: index() },
];

const isDialogOpen = ref(false);
const editingBudget = ref<Budget | null>(null);
const isDeleteDialogOpen = ref(false);
const deletingBudget = ref<Budget | null>(null);
const deletingBudgetId = ref<number | null>(null);
const attachmentInput = ref<HTMLInputElement | null>(null);
const approvalMemoPdfInput = ref<HTMLInputElement | null>(null);
const deleteAttachmentInput = ref<HTMLInputElement | null>(null);

const selectedMonth = ref(props.filters.month);
const selectedYear = ref(props.filters.year);
const selectedDepartment = ref<number | 'all'>(
    props.filters.department ?? 'all',
);
const currentAction = computed(() =>
    editingBudget.value ? 'update' : 'create',
);

const form = useForm({
    department_id:
        props.department_scope.department_id ??
        props.departments[0]?.id ??
        null,
    category_id: props.categories[0]?.id ?? null,
    month: props.filters.month,
    year: props.filters.year,
    amount_limit: '',
    approval_memo_id: null as number | null,
    approval_memo_pdf: null as File | null,
    remarks: '',
    attachments: [] as File[],
});

const deleteForm = useForm({
    department_id: null as number | null,
    target_id: null as number | null,
    remarks: '',
    attachments: [] as File[],
});

const canSelectDepartment = computed(
    () => props.department_scope.can_select_department,
);

const selectedDepartmentLabel = computed(() => {
    if (props.department_scope.is_all_departments) {
        return 'All departments';
    }

    return (
        props.department_scope.selected_department?.name ??
        'Assigned department'
    );
});

const dialogTitle = computed(() =>
    editingBudget.value ? 'Request budget update' : 'Request budget',
);

const dialogDescription = computed(() =>
    editingBudget.value
        ? 'Submit a change request for this monthly spending limit.'
        : 'Submit a new monthly spending limit request for one expense category.',
);

const eligibleApprovalMemos = computed(() =>
    props.available_approval_memos.filter(
        (approvalMemo) =>
            approvalMemo.module === 'budget' &&
            approvalMemo.action === currentAction.value &&
            approvalMemo.department_id === form.department_id,
    ),
);
const selectedApprovalMemo = computed(
    () =>
        eligibleApprovalMemos.value.find(
            (approvalMemo) => approvalMemo.id === form.approval_memo_id,
        ) ?? null,
);
const formAttachmentError = computed(() => findAttachmentError(form.errors));
const approvalMemoPdfError = computed(
    () => form.errors.approval_memo_pdf ?? undefined,
);
const deleteAttachmentError = computed(() =>
    findAttachmentError(deleteForm.errors),
);

watch(
    [() => form.department_id, currentAction],
    () => {
        if (
            eligibleApprovalMemos.value.some(
                (approvalMemo) => approvalMemo.id === form.approval_memo_id,
            )
        ) {
            return;
        }

        if (form.approval_memo_id !== null) {
            form.approval_memo_pdf = null;
            clearFileInput(approvalMemoPdfInput.value);
        }

        form.approval_memo_id = null;
    },
    { immediate: true },
);

const clearFileInput = (input: HTMLInputElement | null) => {
    if (input !== null) {
        input.value = '';
    }
};

const findAttachmentError = (errors: Record<string, string>) =>
    errors.attachments ??
    Object.entries(errors).find(([key]) => key.startsWith('attachments.'))?.[1] ??
    null;

const mergeAttachments = (current: File[], files: FileList | null) =>
    [...current, ...Array.from(files ?? [])].slice(0, 5);

const handleAttachmentChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.attachments = mergeAttachments(form.attachments, target.files);
    clearFileInput(target);
};

const handleApprovalMemoPdfChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.approval_memo_pdf = target.files?.[0] ?? null;
    clearFileInput(target);
};

const clearApprovalMemoPdf = () => {
    form.approval_memo_pdf = null;
    clearFileInput(approvalMemoPdfInput.value);
};

const removeAttachment = (index: number) => {
    form.attachments = form.attachments.filter(
        (_, currentIndex) => currentIndex !== index,
    );
};

const handleDeleteAttachmentChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    deleteForm.attachments = mergeAttachments(
        deleteForm.attachments,
        target.files,
    );
    clearFileInput(target);
};

const removeDeleteAttachment = (index: number) => {
    deleteForm.attachments = deleteForm.attachments.filter(
        (_, currentIndex) => currentIndex !== index,
    );
};

const applyFilters = () => {
    router.get(
        index.url({
            query: {
                month: selectedMonth.value,
                year: selectedYear.value,
                department:
                    selectedDepartment.value === 'all'
                        ? undefined
                        : selectedDepartment.value,
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

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.department_id =
        props.department_scope.department_id ??
        props.departments[0]?.id ??
        null;
    form.category_id = props.categories[0]?.id ?? null;
    form.month = selectedMonth.value;
    form.year = selectedYear.value;
    form.approval_memo_id = null;
    form.approval_memo_pdf = null;
    form.remarks = '';
    form.attachments = [];
    clearFileInput(attachmentInput.value);
    clearFileInput(approvalMemoPdfInput.value);
};

const openCreateDialog = () => {
    editingBudget.value = null;
    resetForm();

    if (canSelectDepartment.value && selectedDepartment.value !== 'all') {
        form.department_id = selectedDepartment.value;
    }

    isDialogOpen.value = true;
};

const openEditDialog = (budget: Budget) => {
    editingBudget.value = budget;
    form.department_id = budget.department_id;
    form.category_id = budget.category_id;
    form.month = budget.month;
    form.year = budget.year;
    form.amount_limit = budget.amount_limit.toFixed(2);
    form.approval_memo_id = null;
    form.approval_memo_pdf = null;
    form.remarks = '';
    form.attachments = [];
    form.clearErrors();
    clearFileInput(attachmentInput.value);
    clearFileInput(approvalMemoPdfInput.value);
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    editingBudget.value = null;
    resetForm();
};

const resetDeleteForm = () => {
    deleteForm.reset();
    deleteForm.clearErrors();
    deleteForm.department_id = null;
    deleteForm.target_id = null;
    deleteForm.remarks = '';
    deleteForm.attachments = [];
    deletingBudget.value = null;
    clearFileInput(deleteAttachmentInput.value);
};

const openDeleteDialog = (budget: Budget) => {
    deletingBudget.value = budget;
    deleteForm.department_id = budget.department_id;
    deleteForm.target_id = budget.id;
    deleteForm.remarks = '';
    deleteForm.attachments = [];
    deleteForm.clearErrors();
    clearFileInput(deleteAttachmentInput.value);
    isDeleteDialogOpen.value = true;
};

const submit = (autoSubmit: boolean) => {
    form.transform((data) => ({
        ...data,
        module: 'budget',
        action: editingBudget.value ? 'update' : 'create',
        target_id: editingBudget.value?.id ?? null,
        auto_submit: autoSubmit,
    })).post(storeApprovalVoucher().url, {
        preserveScroll: true,
        forceFormData: true,
    });
};

const submitDeleteRequest = () => {
    if (deletingBudget.value === null || deletingBudgetId.value !== null) {
        return;
    }

    deletingBudgetId.value = deletingBudget.value.id;

    deleteForm.transform((data) => ({
        ...data,
        module: 'budget',
        action: 'delete',
        target_id: deletingBudget.value?.id ?? data.target_id,
        department_id: deletingBudget.value?.department_id ?? data.department_id,
        auto_submit: true,
    })).post(storeApprovalVoucher().url, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            deletingBudgetId.value = null;
        },
    });
};

const budgetStatus = (budget: Budget) => {
    if (budget.is_over_budget) {
        return { label: 'Over budget', variant: 'destructive' as const };
    }

    if (budget.percentage_used >= 80) {
        return { label: 'Near limit', variant: 'secondary' as const };
    }

    return { label: 'On track', variant: 'outline' as const };
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
                                Budgets
                            </CardTitle>
                            <CardDescription>
                                Final approved monthly limits by department.
                                Changes now flow through approval vouchers.
                            </CardDescription>
                        </div>

                        <Dialog v-model:open="isDialogOpen">
                            <DialogTrigger as-child>
                                <Button
                                    class="w-full sm:w-auto"
                                    @click="openCreateDialog"
                                >
                                    <Plus class="mr-2 size-4" />
                                    Request budget
                                </Button>
                            </DialogTrigger>

                            <DialogContent class="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                                    <DialogDescription>
                                        {{ dialogDescription }}
                                        Save a draft without a memo, or select
                                        an approved memo source and upload its
                                        PDF before submission.
                                    </DialogDescription>
                                </DialogHeader>

                                <form
                                    class="space-y-5"
                                    @submit.prevent="submit(false)"
                                >
                                    <div
                                        v-if="canSelectDepartment"
                                        class="grid gap-2"
                                    >
                                        <Label for="budget-department"
                                            >Department</Label
                                        >
                                        <Select v-model="form.department_id">
                                            <SelectTrigger
                                                id="budget-department"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select a department"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="department in departments"
                                                    :key="department.id"
                                                    :value="department.id"
                                                >
                                                    {{ department.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="form.errors.department_id"
                                        />
                                    </div>

                                    <div
                                        v-else
                                        class="rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                                    >
                                        <span
                                            class="font-medium text-foreground"
                                            >Department:</span
                                        >
                                        {{ selectedDepartmentLabel }}
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="budget-category"
                                            >Category</Label
                                        >
                                        <Select v-model="form.category_id">
                                            <SelectTrigger
                                                id="budget-category"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select a category"
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
                                            :message="form.errors.category_id"
                                        />
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="budget-month"
                                                >Month</Label
                                            >
                                            <Select v-model="form.month">
                                                <SelectTrigger
                                                    id="budget-month"
                                                    class="w-full"
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
                                                :message="form.errors.month"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="budget-year"
                                                >Year</Label
                                            >
                                            <Select v-model="form.year">
                                                <SelectTrigger
                                                    id="budget-year"
                                                    class="w-full"
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
                                                :message="form.errors.year"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="budget-amount"
                                            >Monthly limit</Label
                                        >
                                        <Input
                                            id="budget-amount"
                                            v-model="form.amount_limit"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            required
                                        />
                                        <InputError
                                            :message="form.errors.amount_limit"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="budget-remarks"
                                            >Remarks</Label
                                        >
                                        <textarea
                                            id="budget-remarks"
                                            v-model="form.remarks"
                                            class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                        <InputError
                                            :message="form.errors.remarks"
                                        />
                                    </div>

                                    <div class="grid gap-2 rounded-lg border p-4">
                                        <div
                                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                        >
                                            <div class="space-y-1">
                                                <Label for="budget-approval-memo"
                                                    >Approval memo</Label
                                                >
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Save draft without a memo,
                                                    then select an approved
                                                    budget {{ currentAction }}
                                                    memo source and upload its
                                                    PDF before submission.
                                                </p>
                                            </div>
                                            <Button
                                                as-child
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link :href="approvalMemoIndex()">
                                                    Request memo
                                                </Link>
                                            </Button>
                                        </div>
                                        <Select
                                            v-model="form.approval_memo_id"
                                        >
                                            <SelectTrigger
                                                id="budget-approval-memo"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select approved memo for submission"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="approvalMemo in eligibleApprovalMemos"
                                                    :key="approvalMemo.id"
                                                    :value="approvalMemo.id"
                                                >
                                                    {{ approvalMemo.memo_no }} -
                                                    {{
                                                        approvalMemo.department_name ??
                                                        approvalMemo.module_label
                                                    }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <div
                                            v-if="selectedApprovalMemo"
                                            class="rounded-md border bg-muted/20 p-3 text-sm"
                                        >
                                            <div class="font-medium">
                                                {{ selectedApprovalMemo.memo_no }}
                                            </div>
                                            <div class="mt-1 text-xs text-muted-foreground">
                                                Approved:
                                                {{
                                                    selectedApprovalMemo.approved_at ??
                                                    '-'
                                                }}
                                            </div>
                                            <Button
                                                v-if="
                                                    selectedApprovalMemo.download_url
                                                "
                                                as-child
                                                variant="outline"
                                                size="sm"
                                                class="mt-3"
                                            >
                                                <a
                                                    :href="
                                                        selectedApprovalMemo.download_url
                                                    "
                                                >
                                                    <Download class="mr-2 size-4" />
                                                    Download PDF
                                                </a>
                                            </Button>
                                        </div>
                                        <p
                                            v-if="eligibleApprovalMemos.length === 0"
                                            class="text-xs text-muted-foreground"
                                        >
                                            No approved budget
                                            {{ currentAction }} memos are
                                            available for this department yet.
                                        </p>
                                        <InputError
                                            :message="
                                                form.errors.approval_memo_id
                                            "
                                        />
                                        <div class="grid gap-2">
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <Label for="budget-approval-memo-pdf">
                                                    Approval memo PDF
                                                </Label>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    PDF only
                                                </span>
                                            </div>
                                            <input
                                                id="budget-approval-memo-pdf"
                                                ref="approvalMemoPdfInput"
                                                type="file"
                                                class="block w-full cursor-pointer rounded-md border border-input bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium"
                                                :accept="PDF_ONLY_ACCEPT"
                                                @change="handleApprovalMemoPdfChange"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                Open the selected memo print page, save it as PDF, then upload that file here before submission.
                                            </p>
                                            <InputError
                                                :message="
                                                    approvalMemoPdfError
                                                "
                                            />
                                            <div
                                                v-if="form.approval_memo_pdf"
                                                class="flex items-start justify-between gap-3 rounded-md border bg-muted/20 px-3 py-2"
                                            >
                                                <div
                                                    class="flex items-start gap-2"
                                                >
                                                    <FileUp
                                                        class="mt-0.5 size-4 text-muted-foreground"
                                                    />
                                                    <div>
                                                        <div
                                                            class="text-sm font-medium"
                                                        >
                                                            {{
                                                                form
                                                                    .approval_memo_pdf
                                                                    .name
                                                            }}
                                                        </div>
                                                        <div
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                formatFileSize(
                                                                    form
                                                                        .approval_memo_pdf
                                                                        .size,
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        clearApprovalMemoPdf()
                                                    "
                                                >
                                                    <X class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <Label for="budget-files"
                                                >Supporting documents</Label
                                            >
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ form.attachments.length }}/5
                                                selected
                                            </span>
                                        </div>
                                        <input
                                            id="budget-files"
                                            ref="attachmentInput"
                                            type="file"
                                            class="block w-full cursor-pointer rounded-md border border-input bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium"
                                            :accept="
                                                SUPPORTING_DOCUMENT_ACCEPT
                                            "
                                            multiple
                                            @change="handleAttachmentChange"
                                        />
                                        <p
                                            class="flex items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <Paperclip class="size-3.5" />
                                            PDF, JPG, PNG, or WEBP up to 10 MB
                                            each.
                                        </p>
                                        <InputError
                                            :message="formAttachmentError"
                                        />
                                        <div
                                            v-if="form.attachments.length > 0"
                                            class="space-y-2 rounded-lg border p-3"
                                        >
                                            <div
                                                v-for="(
                                                    attachment, index
                                                ) in form.attachments"
                                                :key="
                                                    `${attachment.name}-${index}`
                                                "
                                                class="flex items-start justify-between gap-3 rounded-md border bg-muted/20 px-3 py-2"
                                            >
                                                <div
                                                    class="flex items-start gap-2"
                                                >
                                                    <FileUp
                                                        class="mt-0.5 size-4 text-muted-foreground"
                                                    />
                                                    <div>
                                                        <div
                                                            class="text-sm font-medium"
                                                        >
                                                            {{
                                                                attachment.name
                                                            }}
                                                        </div>
                                                        <div
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                formatFileSize(
                                                                    attachment.size,
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        removeAttachment(index)
                                                    "
                                                >
                                                    <X class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>

                                    <DialogFooter class="gap-2 sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            @click="closeDialog"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            :disabled="form.processing"
                                            @click="submit(false)"
                                        >
                                            <Spinner v-if="form.processing" />
                                            Save draft
                                        </Button>
                                        <Button
                                            type="button"
                                            :disabled="
                                                form.processing ||
                                                !form.approval_memo_id ||
                                                !form.approval_memo_pdf
                                            "
                                            @click="submit(true)"
                                        >
                                            <Spinner v-if="form.processing" />
                                            Submit request
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

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
                                    <DialogTitle>
                                        Create delete request
                                    </DialogTitle>
                                    <DialogDescription>
                                        Add context and optional supporting
                                        files before archiving this budget
                                        through approval.
                                    </DialogDescription>
                                </DialogHeader>

                                <form
                                    class="space-y-4"
                                    @submit.prevent="submitDeleteRequest"
                                >
                                    <div
                                        v-if="deletingBudget"
                                        class="rounded-lg border bg-muted/20 p-4 text-sm"
                                    >
                                        <div class="font-medium">
                                            {{ deletingBudget.category_name }}
                                        </div>
                                        <div class="mt-1 text-muted-foreground">
                                            {{ deletingBudget.month }}/{{
                                                deletingBudget.year
                                            }}
                                            Â-
                                            {{
                                                deletingBudget.department?.name ??
                                                selectedDepartmentLabel
                                            }}
                                        </div>
                                        <div class="mt-2 text-muted-foreground">
                                            Limit:
                                            {{
                                                deletingBudget.amount_limit.toFixed(
                                                    2,
                                                )
                                            }}
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="delete-budget-remarks"
                                            >Remarks</Label
                                        >
                                        <textarea
                                            id="delete-budget-remarks"
                                            v-model="deleteForm.remarks"
                                            class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                        <InputError
                                            :message="deleteForm.errors.remarks"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <Label for="delete-budget-files"
                                                >Supporting documents</Label
                                            >
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    deleteForm.attachments
                                                        .length
                                                }}/5 selected
                                            </span>
                                        </div>
                                        <input
                                            id="delete-budget-files"
                                            ref="deleteAttachmentInput"
                                            type="file"
                                            class="block w-full cursor-pointer rounded-md border border-input bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium"
                                            :accept="
                                                SUPPORTING_DOCUMENT_ACCEPT
                                            "
                                            multiple
                                            @change="
                                                handleDeleteAttachmentChange
                                            "
                                        />
                                        <p
                                            class="flex items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <Paperclip class="size-3.5" />
                                            PDF, JPG, PNG, or WEBP up to 10 MB
                                            each.
                                        </p>
                                        <InputError
                                            :message="deleteAttachmentError"
                                        />
                                        <div
                                            v-if="
                                                deleteForm.attachments.length >
                                                0
                                            "
                                            class="space-y-2 rounded-lg border p-3"
                                        >
                                            <div
                                                v-for="(
                                                    attachment, index
                                                ) in deleteForm.attachments"
                                                :key="
                                                    `${attachment.name}-${index}`
                                                "
                                                class="flex items-start justify-between gap-3 rounded-md border bg-muted/20 px-3 py-2"
                                            >
                                                <div
                                                    class="flex items-start gap-2"
                                                >
                                                    <FileUp
                                                        class="mt-0.5 size-4 text-muted-foreground"
                                                    />
                                                    <div>
                                                        <div
                                                            class="text-sm font-medium"
                                                        >
                                                            {{
                                                                attachment.name
                                                            }}
                                                        </div>
                                                        <div
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                formatFileSize(
                                                                    attachment.size,
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        removeDeleteAttachment(
                                                            index,
                                                        )
                                                    "
                                                >
                                                    <X class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
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
                                            <Spinner
                                                v-if="deleteForm.processing"
                                            />
                                            Create delete request
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription
                            >Choose the period and department
                            context.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="canSelectDepartment" class="grid gap-2">
                            <Label for="filter-budget-department"
                                >Department</Label
                            >
                            <Select
                                :model-value="selectedDepartment"
                                @update:model-value="
                                    selectedDepartment = $event as
                                        | number
                                        | 'all';
                                    applyFilters();
                                "
                            >
                                <SelectTrigger
                                    id="filter-budget-department"
                                    class="w-full"
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
                                    <SelectTrigger
                                        id="filter-budget-month"
                                        class="w-full"
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
                                    <SelectTrigger
                                        id="filter-budget-year"
                                        class="w-full"
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
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Budget status</CardTitle>
                    <CardDescription>
                        {{ budgets.length }} budget{{
                            budgets.length === 1 ? '' : 's'
                        }}
                        for {{ selectedDepartmentLabel.toLowerCase() }}.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="budgets.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No budgets found for the current filters.
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <div class="overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-border text-sm"
                            >
                                <thead
                                    class="bg-muted/50 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            v-if="canSelectDepartment"
                                            class="px-4 py-3 font-medium"
                                        >
                                            Department
                                        </th>
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
                                            v-if="canSelectDepartment"
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ budget.department?.name ?? '-' }}
                                        </td>
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
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        openEditDialog(budget)
                                                    "
                                                >
                                                    <Pencil
                                                        class="mr-2 size-4"
                                                    />
                                                    Request update
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    :disabled="
                                                        deletingBudgetId ===
                                                        budget.id
                                                    "
                                                    @click="openDeleteDialog(budget)"
                                                >
                                                    <Spinner
                                                        v-if="
                                                            deletingBudgetId ===
                                                            budget.id
                                                        "
                                                    />
                                                    <Trash2
                                                        v-else
                                                        class="mr-2 size-4"
                                                    />
                                                    Request delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
