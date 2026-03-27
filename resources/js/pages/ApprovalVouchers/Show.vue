<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Download,
    FileUp,
    Paperclip,
    Printer,
    Send,
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
import {
    displayApprovalModuleLabel,
    displayDepartmentName,
} from '@/lib/plain-language';
import { formatFileSize, SUPPORTING_DOCUMENT_ACCEPT } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    approve as approveVoucher,
    index as approvalVoucherIndex,
    print as printVoucher,
    reject as rejectVoucher,
    submit as submitVoucher,
    update as updateVoucher,
} from '@/routes/approval-vouchers';
import type {
    ActivityLogItem,
    ApprovalVoucher,
    ApprovalVoucherPayload,
    BreadcrumbItem,
    Category,
    CategoryType,
    DepartmentOption,
} from '@/types';

type TransactionTypeOption = { value: CategoryType; label: string };

const props = defineProps<{
    approval_voucher: ApprovalVoucher;
    activity_logs: ActivityLogItem[];
    categories: Category[];
    departments: DepartmentOption[];
    transaction_types: TransactionTypeOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Requests', href: approvalVoucherIndex() },
    { title: props.approval_voucher.voucher_no, href: approvalVoucherIndex() },
];

const payload = computed(
    () =>
        props.approval_voucher.after_payload ??
        props.approval_voucher.before_payload,
);
const isTransaction = computed(
    () => props.approval_voucher.module === 'transaction',
);
const isAllocation = computed(
    () => props.approval_voucher.module === 'allocation',
);
const isDelete = computed(() => props.approval_voucher.action === 'delete');
const canEdit = computed(() => props.approval_voucher.permissions.can_edit);
const attachmentInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    module: props.approval_voucher.module,
    action: props.approval_voucher.action,
    target_id: props.approval_voucher.target_id,
    department_id:
        payload.value?.department_id ?? props.approval_voucher.department_id,
    type:
        payload.value && 'type' in payload.value
            ? (payload.value.type as CategoryType)
            : ('expense' as CategoryType),
    category_id:
        payload.value && 'category_id' in payload.value
            ? payload.value.category_id
            : null,
    title: payload.value && 'title' in payload.value ? payload.value.title : '',
    amount:
        payload.value && 'amount' in payload.value
            ? String(payload.value.amount)
            : '',
    description:
        payload.value && 'description' in payload.value
            ? (payload.value.description ?? '')
            : '',
    transaction_date:
        payload.value && 'transaction_date' in payload.value
            ? (payload.value.transaction_date ?? '')
            : '',
    month: payload.value && 'month' in payload.value ? payload.value.month : 1,
    year:
        payload.value && 'year' in payload.value
            ? payload.value.year
            : new Date().getFullYear(),
    amount_limit:
        payload.value && 'amount_limit' in payload.value
            ? String(payload.value.amount_limit)
            : '',
    remarks: props.approval_voucher.remarks ?? '',
    attachments: [] as File[],
    remove_attachment_ids: [] as number[],
});

const approveForm = useForm({ remarks: props.approval_voucher.remarks ?? '' });
const rejectForm = useForm({
    rejection_reason: props.approval_voucher.rejection_reason ?? '',
});
const attachmentError = computed(() => findAttachmentError(form.errors));
const removeAttachmentError = computed(
    () => form.errors.remove_attachment_ids ?? undefined,
);
const markedForRemoval = computed(
    () => new Set(form.remove_attachment_ids.map((id) => Number(id))),
);
const attachmentPreviewCount = computed(
    () =>
        props.approval_voucher.attachments.filter(
            (attachment) => !markedForRemoval.value.has(attachment.id),
        ).length + form.attachments.length,
);

const formCategories = computed(() =>
    props.categories.filter((category) =>
        isTransaction.value
            ? category.type === form.type
            : category.type === 'expense',
    ),
);

watch(
    () => form.type,
    () => {
        if (!isTransaction.value) {
            return;
        }

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

const clearFileInput = (input: HTMLInputElement | null) => {
    if (input !== null) {
        input.value = '';
    }
};

const findAttachmentError = (errors: Record<string, string>) =>
    errors.attachments ??
    Object.entries(errors).find(([key]) =>
        key.startsWith('attachments.'),
    )?.[1] ??
    null;

const handleAttachmentChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.attachments = [
        ...form.attachments,
        ...Array.from(target.files ?? []),
    ].slice(0, 5);

    clearFileInput(target);
};

const removePendingAttachment = (index: number) => {
    form.attachments = form.attachments.filter(
        (_, currentIndex) => currentIndex !== index,
    );
};

const toggleAttachmentRemoval = (attachmentId: number) => {
    if (markedForRemoval.value.has(attachmentId)) {
        form.remove_attachment_ids = form.remove_attachment_ids.filter(
            (id) => id !== attachmentId,
        );

        return;
    }

    form.remove_attachment_ids = [...form.remove_attachment_ids, attachmentId];
};

const nameForDepartment = (id: number | null | undefined) =>
    displayDepartmentName(
        props.departments.find((department) => department.id === id),
        props.departments.find((department) => department.id === id)?.name ??
            displayDepartmentName(props.approval_voucher.department, '-'),
    );
const nameForCategory = (id: number | null | undefined) =>
    props.categories.find((category) => category.id === id)?.name ?? '-';
const monthLabel = (month: number | null | undefined) =>
    month && month >= 1 && month <= 12
        ? new Date(2000, month - 1, 1).toLocaleString('en-US', {
              month: 'long',
          })
        : '-';
const fieldValue = (data: ApprovalVoucherPayload, key: string) => {
    if (!data) {
        return '-';
    }

    if (key === 'department_id') {
        return nameForDepartment(data.department_id);
    }

    if (key === 'category_id' && 'category_id' in data) {
        return nameForCategory(data.category_id);
    }

    if (key === 'month' && 'month' in data) {
        return monthLabel(data.month);
    }

    const value = data[key as keyof typeof data];
    const text = value == null ? '' : String(value);

    return text === '' ? '-' : text;
};
const fields = computed(() =>
    isTransaction.value
        ? [
              'department_id',
              'type',
              'category_id',
              'title',
              'amount',
              'transaction_date',
              'description',
          ]
        : isAllocation.value
          ? ['department_id', 'month', 'year', 'amount_limit']
          : ['department_id', 'category_id', 'month', 'year', 'amount_limit'],
);
const fieldLabel = (key: string) =>
    ({
        department_id: 'Department',
        type: 'Type',
        category_id: 'Category',
        title: 'Title',
        amount: 'Amount',
        transaction_date: 'Date',
        description: 'Description',
        month: 'Month',
        year: 'Year',
        amount_limit: 'Monthly budget',
    })[key] ?? key;
const reviewTitle = computed(() =>
    props.approval_voucher.module === 'transaction'
        ? 'Finance Team review'
        : 'Admin Review',
);

const saveDraft = () =>
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(updateVoucher(props.approval_voucher.id).url, {
            preserveScroll: true,
            forceFormData: true,
        });
const submitRequest = () =>
    router.post(
        submitVoucher(props.approval_voucher.id).url,
        {},
        { preserveScroll: true },
    );
const approveRequest = () =>
    approveForm.patch(approveVoucher(props.approval_voucher.id).url, {
        preserveScroll: true,
    });
const rejectRequest = () =>
    rejectForm.patch(rejectVoucher(props.approval_voucher.id).url, {
        preserveScroll: true,
    });
const openPrintView = () => {
    window.open(
        printVoucher(props.approval_voucher.id).url,
        '_blank',
        'noopener,noreferrer',
    );
};
const formatDateTime = (value: string | null) => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value.replace(' ', 'T'));

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
const eventLabel = (event: string) =>
    event
        .split('.')
        .pop()
        ?.replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase()) ?? event;
const eventVariant = (event: string) => {
    if (event.endsWith('rejected')) {
        return 'destructive' as const;
    }

    if (
        event.includes('submitted') ||
        event.includes('applied') ||
        event.includes('voided') ||
        event.includes('archived')
    ) {
        return 'secondary' as const;
    }

    return 'outline' as const;
};
</script>

<template>
    <Head :title="approval_voucher.voucher_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="space-y-2">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="w-fit px-0"
                    >
                        <Link :href="approvalVoucherIndex()"
                            ><ArrowLeft class="mr-2 size-4" />Back to
                            requests</Link
                        >
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold">
                            {{ approval_voucher.voucher_no }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ approval_voucher.subject }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge>{{ approval_voucher.status_label }}</Badge>
                        <Badge variant="outline">{{
                            displayApprovalModuleLabel(
                                approval_voucher.module,
                                approval_voucher.module_label,
                            )
                        }}</Badge>
                        <Badge variant="outline">{{
                            approval_voucher.action_label
                        }}</Badge>
                    </div>
                </div>
                <div class="space-y-1 text-sm text-muted-foreground">
                    <div class="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="openPrintView"
                        >
                            <Printer class="mr-2 size-4" />
                            Print
                        </Button>
                    </div>
                    <div>
                        <span class="font-medium text-foreground"
                            >Requested by:</span
                        >
                        {{ approval_voucher.requested_by_user?.name ?? '-' }}
                    </div>
                    <div>
                        <span class="font-medium text-foreground"
                            >Department:</span
                        >
                        {{
                            displayDepartmentName(
                                approval_voucher.department,
                                '-',
                            )
                        }}
                    </div>
                    <div>
                        <span class="font-medium text-foreground"
                            >Submitted:</span
                        >
                        {{ approval_voucher.submitted_at ?? '-' }}
                    </div>
                    <div>
                        <span class="font-medium text-foreground"
                            >Applied:</span
                        >
                        {{ approval_voucher.applied_at ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Request details</CardTitle>
                        <CardDescription
                            >Review what will change in this
                            request.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-2 rounded-lg border p-4">
                            <div class="text-sm font-medium">Current</div>
                            <div
                                v-if="approval_voucher.before_payload === null"
                                class="text-sm text-muted-foreground"
                            >
                                No saved record yet.
                            </div>
                            <div v-else class="space-y-2 text-sm">
                                <div
                                    v-for="field in fields"
                                    :key="`before-${field}`"
                                    class="flex justify-between gap-4"
                                >
                                    <span class="text-muted-foreground">{{
                                        fieldLabel(field)
                                    }}</span>
                                    <span class="text-right font-medium">{{
                                        fieldValue(
                                            approval_voucher.before_payload,
                                            field,
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 rounded-lg border p-4">
                            <div class="text-sm font-medium">Requested</div>
                            <div
                                v-if="approval_voucher.after_payload === null"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    isDelete
                                        ? 'This request will remove the saved record.'
                                        : 'No new changes saved.'
                                }}
                            </div>
                            <div v-else class="space-y-2 text-sm">
                                <div
                                    v-for="field in fields"
                                    :key="`after-${field}`"
                                    class="flex justify-between gap-4"
                                >
                                    <span class="text-muted-foreground">{{
                                        fieldLabel(field)
                                    }}</span>
                                    <span class="text-right font-medium">{{
                                        fieldValue(
                                            approval_voucher.after_payload,
                                            field,
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium">Request notes</div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    approval_voucher.remarks ??
                                    'No notes provided.'
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium">
                                Rejection reason
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    approval_voucher.rejection_reason ??
                                    'No rejection recorded.'
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4">
                    <Card
                        v-if="canEdit"
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>Edit draft</CardTitle>
                            <CardDescription
                                >Update the request before you submit
                                it.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="saveDraft">
                                <div
                                    v-if="isTransaction && !isDelete"
                                    class="grid gap-2"
                                >
                                    <Label>Department</Label>
                                    <Select v-model="form.department_id">
                                        <SelectTrigger
                                            ><SelectValue
                                                placeholder="Select department"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="department in departments"
                                                :key="department.id"
                                                :value="department.id"
                                                >{{
                                                    displayDepartmentName(
                                                        department,
                                                        department.name,
                                                    )
                                                }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.department_id"
                                    />
                                </div>
                                <div
                                    v-else-if="!isDelete"
                                    class="rounded-lg border bg-muted/20 px-3 py-2 text-sm text-muted-foreground"
                                >
                                    <span class="font-medium text-foreground">
                                        Department:
                                    </span>
                                    {{
                                        approval_voucher.department
                                            ? displayDepartmentName(
                                                  approval_voucher.department,
                                                  approval_voucher.department
                                                      .name,
                                              )
                                            : nameForDepartment(
                                                  form.department_id,
                                              )
                                    }}
                                </div>
                                <template v-if="isTransaction && !isDelete">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label>Type</Label>
                                            <Select v-model="form.type">
                                                <SelectTrigger
                                                    ><SelectValue
                                                        placeholder="Select type"
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="type in transaction_types"
                                                        :key="type.value"
                                                        :value="type.value"
                                                        >{{
                                                            type.label
                                                        }}</SelectItem
                                                    >
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                :message="form.errors.type"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Category</Label>
                                            <Select v-model="form.category_id">
                                                <SelectTrigger
                                                    ><SelectValue
                                                        placeholder="Select category"
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="category in formCategories"
                                                        :key="category.id"
                                                        :value="category.id"
                                                        >{{
                                                            category.name
                                                        }}</SelectItem
                                                    >
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                :message="
                                                    form.errors.category_id
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Title</Label
                                        ><Input
                                            v-model="form.title"
                                            type="text"
                                        /><InputError
                                            :message="form.errors.title"
                                        />
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label>Amount</Label
                                            ><Input
                                                v-model="form.amount"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                            /><InputError
                                                :message="form.errors.amount"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Date</Label
                                            ><Input
                                                v-model="form.transaction_date"
                                                type="date"
                                            /><InputError
                                                :message="
                                                    form.errors.transaction_date
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Description</Label
                                        ><Input
                                            v-model="form.description"
                                            type="text"
                                        /><InputError
                                            :message="form.errors.description"
                                        />
                                    </div>
                                </template>
                                <template v-else-if="isAllocation && !isDelete">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label>Month</Label
                                            ><Select v-model="form.month"
                                                ><SelectTrigger
                                                    ><SelectValue
                                                        placeholder="Select month" /></SelectTrigger
                                                ><SelectContent
                                                    ><SelectItem
                                                        v-for="month in 12"
                                                        :key="month"
                                                        :value="month"
                                                        >{{
                                                            monthLabel(month)
                                                        }}</SelectItem
                                                    ></SelectContent
                                                ></Select
                                            ><InputError
                                                :message="form.errors.month"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Year</Label
                                            ><Input
                                                v-model="form.year"
                                                type="number"
                                                min="1900"
                                                max="2100"
                                            /><InputError
                                                :message="form.errors.year"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Monthly budget</Label
                                        ><Input
                                            v-model="form.amount_limit"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                        /><InputError
                                            :message="form.errors.amount_limit"
                                        />
                                    </div>
                                </template>
                                <template
                                    v-else-if="!isTransaction && !isDelete"
                                >
                                    <div class="grid gap-2">
                                        <Label>Category</Label>
                                        <Select v-model="form.category_id">
                                            <SelectTrigger
                                                ><SelectValue
                                                    placeholder="Select category"
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="category in formCategories"
                                                    :key="category.id"
                                                    :value="category.id"
                                                    >{{
                                                        category.name
                                                    }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="form.errors.category_id"
                                        />
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label>Month</Label
                                            ><Select v-model="form.month"
                                                ><SelectTrigger
                                                    ><SelectValue
                                                        placeholder="Select month" /></SelectTrigger
                                                ><SelectContent
                                                    ><SelectItem
                                                        v-for="month in 12"
                                                        :key="month"
                                                        :value="month"
                                                        >{{
                                                            monthLabel(month)
                                                        }}</SelectItem
                                                    ></SelectContent
                                                ></Select
                                            ><InputError
                                                :message="form.errors.month"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Year</Label
                                            ><Input
                                                v-model="form.year"
                                                type="number"
                                                min="1900"
                                                max="2100"
                                            /><InputError
                                                :message="form.errors.year"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Monthly budget</Label
                                        ><Input
                                            v-model="form.amount_limit"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                        /><InputError
                                            :message="form.errors.amount_limit"
                                        />
                                    </div>
                                </template>
                                <div class="grid gap-2">
                                    <Label>Notes</Label>
                                    <textarea
                                        v-model="form.remarks"
                                        class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                    <InputError
                                        :message="form.errors.remarks"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        :disabled="form.processing"
                                        ><Spinner v-if="form.processing" />Save
                                        draft</Button
                                    >
                                    <Button
                                        v-if="
                                            approval_voucher.permissions
                                                .can_submit
                                        "
                                        type="button"
                                        variant="outline"
                                        :disabled="form.processing"
                                        @click="submitRequest"
                                        ><Send
                                            class="mr-2 size-4"
                                        />Submit</Button
                                    >
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card
                        v-if="
                            approval_voucher.permissions.can_approve ||
                            approval_voucher.permissions.can_reject
                        "
                        class="border-sidebar-border/70 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle>{{ reviewTitle }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="approval_voucher.permissions.can_approve"
                                class="space-y-2"
                            >
                                <Label>Notes</Label>
                                <textarea
                                    v-model="approveForm.remarks"
                                    class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <InputError
                                    :message="approveForm.errors.remarks"
                                />
                                <Button
                                    :disabled="approveForm.processing"
                                    @click="approveRequest"
                                    ><Spinner
                                        v-if="approveForm.processing"
                                    /><Check class="mr-2 size-4" />Approve
                                    request</Button
                                >
                            </div>
                            <div
                                v-if="approval_voucher.permissions.can_reject"
                                class="space-y-2"
                            >
                                <Label>Rejection reason</Label>
                                <textarea
                                    v-model="rejectForm.rejection_reason"
                                    class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <InputError
                                    :message="
                                        rejectForm.errors.rejection_reason
                                    "
                                />
                                <Button
                                    variant="destructive"
                                    :disabled="rejectForm.processing"
                                    @click="rejectRequest"
                                    ><Spinner v-if="rejectForm.processing" /><X
                                        class="mr-2 size-4"
                                    />Reject</Button
                                >
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2">
                            <Paperclip class="size-4" />
                            Files
                        </CardTitle>
                        <CardDescription>
                            {{
                                canEdit
                                    ? `${attachmentPreviewCount}/5 files will stay on this request after the next draft save.`
                                    : `${approval_voucher.attachments.length} file${approval_voucher.attachments.length === 1 ? '' : 's'} attached to this request.`
                            }}
                        </CardDescription>
                    </div>
                    <Badge variant="outline">
                        {{ approval_voucher.attachments.length }} saved files
                    </Badge>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="canEdit" class="grid gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <Label for="voucher-attachments"> Add files </Label>
                            <span class="text-xs text-muted-foreground">
                                {{ form.attachments.length }}/5 selected
                            </span>
                        </div>
                        <input
                            id="voucher-attachments"
                            ref="attachmentInput"
                            type="file"
                            class="block w-full cursor-pointer rounded-md border border-input bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium"
                            :accept="SUPPORTING_DOCUMENT_ACCEPT"
                            multiple
                            @change="handleAttachmentChange"
                        />
                        <p
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Paperclip class="size-3.5" />
                            PDF, JPG, PNG, or WEBP up to 10 MB each.
                        </p>
                        <InputError :message="attachmentError" />
                        <InputError :message="removeAttachmentError" />
                    </div>

                    <div
                        v-if="
                            approval_voucher.attachments.length === 0 &&
                            form.attachments.length === 0
                        "
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No files added.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="attachment in approval_voucher.attachments"
                            :key="attachment.id"
                            class="rounded-lg border px-4 py-3"
                            :class="
                                markedForRemoval.has(attachment.id)
                                    ? 'border-destructive/40 bg-destructive/5'
                                    : ''
                            "
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex items-start gap-3">
                                    <FileUp
                                        class="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div>
                                        <div class="text-sm font-medium">
                                            {{ attachment.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatFileSize(
                                                    attachment.size_bytes,
                                                )
                                            }}
                                            Â-
                                            {{ attachment.mime_type }}
                                            Â-
                                            {{
                                                formatDateTime(
                                                    attachment.uploaded_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                    >
                                        <a
                                            :href="attachment.download_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <Download class="mr-2 size-4" />
                                            Download
                                        </a>
                                    </Button>
                                    <Button
                                        v-if="canEdit"
                                        type="button"
                                        :variant="
                                            markedForRemoval.has(attachment.id)
                                                ? 'secondary'
                                                : 'ghost'
                                        "
                                        size="sm"
                                        @click="
                                            toggleAttachmentRemoval(
                                                attachment.id,
                                            )
                                        "
                                    >
                                        {{
                                            markedForRemoval.has(attachment.id)
                                                ? 'Undo remove'
                                                : 'Remove on save'
                                        }}
                                    </Button>
                                </div>
                            </div>
                            <p
                                v-if="markedForRemoval.has(attachment.id)"
                                class="mt-3 text-xs text-destructive"
                            >
                                This file will be removed the next time you save
                                the draft.
                            </p>
                        </div>

                        <div
                            v-for="(attachment, index) in form.attachments"
                            :key="`pending-${attachment.name}-${index}`"
                            class="rounded-lg border border-dashed px-4 py-3"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex items-start gap-3">
                                    <FileUp
                                        class="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div>
                                        <div class="text-sm font-medium">
                                            {{ attachment.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatFileSize(attachment.size)
                                            }}
                                            Â- Pending upload
                                        </div>
                                    </div>
                                </div>
                                <Button
                                    v-if="canEdit"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removePendingAttachment(index)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>History</CardTitle>
                    <CardDescription
                        >Latest workflow activity for this
                        request.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="activity_logs.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No workflow history recorded yet.
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-for="activityLog in activity_logs"
                            :key="activityLog.id"
                            class="rounded-lg border p-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Badge
                                            :variant="
                                                eventVariant(activityLog.event)
                                            "
                                        >
                                            {{ eventLabel(activityLog.event) }}
                                        </Badge>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                activityLog.actor?.name ??
                                                'System'
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ activityLog.summary }}
                                    </p>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDateTime(activityLog.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
