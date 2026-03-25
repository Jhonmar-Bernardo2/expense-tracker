<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Paperclip, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatFileSize } from '@/lib/utils';
import {
    index as approvalVoucherIndex,
    show as approvalVoucherShow,
} from '@/routes/approval-vouchers';
import type {
    ApprovalVoucher,
    ApprovalVoucherPayload,
    Category,
    DepartmentOption,
} from '@/types';

type DisplayFieldKey =
    | 'department_id'
    | 'type'
    | 'category_id'
    | 'title'
    | 'amount'
    | 'transaction_date'
    | 'description'
    | 'month'
    | 'year'
    | 'amount_limit';

const props = defineProps<{
    approval_voucher: ApprovalVoucher;
    categories: Category[];
    departments: DepartmentOption[];
}>();

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

const currentPayload = computed(
    () =>
        props.approval_voucher.after_payload ??
        props.approval_voucher.before_payload,
);
const isTransaction = computed(
    () => props.approval_voucher.module === 'transaction',
);
const isDelete = computed(() => props.approval_voucher.action === 'delete');
const actionSummary = computed(() => {
    if (props.approval_voucher.action === 'create') {
        return 'New record request';
    }

    if (props.approval_voucher.action === 'update') {
        return 'Change request';
    }

    return 'Delete request';
});
const fields = computed<DisplayFieldKey[]>(() =>
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
        : ['department_id', 'category_id', 'month', 'year', 'amount_limit'],
);

const formatText = (value: string) =>
    value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

const monthLabel = (month: number | null | undefined) =>
    month && month >= 1 && month <= 12
        ? new Date(2000, month - 1, 1).toLocaleString('en-US', {
              month: 'long',
          })
        : '-';

const formatDate = (value: string | null | undefined) => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(`${value}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (value: string | null | undefined) => {
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

const statusTone = (status: ApprovalVoucher['status']) => {
    if (status === 'approved') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'pending_approval') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (status === 'rejected') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
};

const nameForDepartment = (id: number | null | undefined) =>
    props.departments.find((department) => department.id === id)?.name ??
    props.approval_voucher.department?.name ??
    '-';

const nameForCategory = (id: number | null | undefined) =>
    props.categories.find((category) => category.id === id)?.name ?? '-';

const fieldLabel = (key: DisplayFieldKey) =>
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
        amount_limit: 'Monthly limit',
    })[key];

const fieldValue = (data: ApprovalVoucherPayload, key: DisplayFieldKey) => {
    if (!data) {
        return '-';
    }

    if (key === 'department_id') {
        return nameForDepartment(data.department_id);
    }

    if (key === 'category_id') {
        return nameForCategory(data.category_id);
    }

    if (key === 'type' && 'type' in data) {
        return formatText(data.type);
    }

    if (key === 'month' && 'month' in data) {
        return monthLabel(data.month);
    }

    if (key === 'transaction_date' && 'transaction_date' in data) {
        return formatDate(data.transaction_date);
    }

    if (key === 'amount' && 'amount' in data) {
        return currencyFormatter.format(Number(data.amount));
    }

    if (key === 'amount_limit' && 'amount_limit' in data) {
        return currencyFormatter.format(Number(data.amount_limit));
    }

    const value = data[key as keyof typeof data];
    const text = value == null ? '' : String(value);

    return text === '' ? '-' : text;
};

const printDocument = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${approval_voucher.voucher_no} Print`" />

    <div
        class="print-page min-h-screen bg-stone-100 px-4 py-6 text-slate-900 sm:px-6"
    >
        <div
            class="print-toolbar mx-auto mb-4 flex max-w-5xl flex-wrap items-center justify-between gap-3"
        >
            <Button as-child variant="ghost" size="sm">
                <Link :href="approvalVoucherShow(approval_voucher.id)">
                    <ArrowLeft class="mr-2 size-4" />
                    Back to voucher
                </Link>
            </Button>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline">
                    <Link :href="approvalVoucherIndex()"> Back to queue </Link>
                </Button>
                <Button type="button" @click="printDocument">
                    <Printer class="mr-2 size-4" />
                    Print now
                </Button>
            </div>
        </div>

        <div
            class="print-document mx-auto max-w-5xl overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl"
        >
            <div
                class="relative overflow-hidden border-b border-slate-200 px-6 py-8 sm:px-10"
            >
                <div class="print-watermark" aria-hidden="true">
                    {{ approval_voucher.status_label }}
                </div>

                <div class="relative z-10 flex flex-col gap-6">
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-3">
                            <div
                                class="text-sm font-semibold tracking-[0.35em] text-slate-500 uppercase"
                            >
                                Expense Tracker
                            </div>
                            <div>
                                <h1
                                    class="text-3xl font-semibold tracking-tight text-slate-950"
                                >
                                    Approval Voucher
                                </h1>
                                <p
                                    class="mt-2 max-w-2xl text-sm leading-6 text-slate-600"
                                >
                                    {{ approval_voucher.subject }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-col items-start gap-3 sm:items-end"
                        >
                            <div
                                class="rounded-full border px-3 py-1 text-xs font-semibold tracking-[0.25em] uppercase"
                                :class="statusTone(approval_voucher.status)"
                            >
                                {{ approval_voucher.status_label }}
                            </div>
                            <div class="text-right">
                                <div
                                    class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                                >
                                    Voucher No.
                                </div>
                                <div
                                    class="mt-1 text-xl font-semibold text-slate-950"
                                >
                                    {{ approval_voucher.voucher_no }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Module
                            </div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{ approval_voucher.module_label }}
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Action
                            </div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{ approval_voucher.action_label }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ actionSummary }}
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Preparer
                            </div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{
                                    approval_voucher.requested_by_user?.name ??
                                    '-'
                                }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{
                                    approval_voucher.requested_by_user?.email ??
                                    'No email available'
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                        >
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Department
                            </div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{
                                    approval_voucher.department?.name ??
                                    nameForDepartment(
                                        currentPayload?.department_id,
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-8 px-6 py-8 sm:px-10">
                <section
                    class="print-section grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div
                            class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                        >
                            Created
                        </div>
                        <div class="mt-2 text-sm font-medium text-slate-900">
                            {{ formatDateTime(approval_voucher.created_at) }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div
                            class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                        >
                            Submitted
                        </div>
                        <div class="mt-2 text-sm font-medium text-slate-900">
                            {{ formatDateTime(approval_voucher.submitted_at) }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div
                            class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                        >
                            Approved
                        </div>
                        <div class="mt-2 text-sm font-medium text-slate-900">
                            {{ formatDateTime(approval_voucher.approved_at) }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div
                            class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                        >
                            Applied
                        </div>
                        <div class="mt-2 text-sm font-medium text-slate-900">
                            {{ formatDateTime(approval_voucher.applied_at) }}
                        </div>
                    </div>
                </section>

                <section class="print-section grid gap-4 xl:grid-cols-2">
                    <div class="rounded-[24px] border border-slate-200">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h2 class="text-lg font-semibold text-slate-950">
                                Before Snapshot
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Original record values before this request.
                            </p>
                        </div>
                        <div class="px-5 py-4">
                            <div
                                v-if="approval_voucher.before_payload === null"
                                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500"
                            >
                                No prior final record snapshot.
                            </div>
                            <dl v-else class="space-y-3">
                                <div
                                    v-for="field in fields"
                                    :key="`before-${field}`"
                                    class="flex items-start justify-between gap-4 border-b border-dashed border-slate-200 pb-3 last:border-b-0 last:pb-0"
                                >
                                    <dt class="text-sm text-slate-500">
                                        {{ fieldLabel(field) }}
                                    </dt>
                                    <dd
                                        class="max-w-[60%] text-right text-sm font-medium text-slate-900"
                                    >
                                        {{
                                            fieldValue(
                                                approval_voucher.before_payload,
                                                field,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="rounded-[24px] border border-slate-200">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h2 class="text-lg font-semibold text-slate-950">
                                After Snapshot
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Requested values that will be applied after
                                approval.
                            </p>
                        </div>
                        <div class="px-5 py-4">
                            <div
                                v-if="approval_voucher.after_payload === null"
                                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500"
                            >
                                {{
                                    isDelete
                                        ? 'Approval will void or archive the final record.'
                                        : 'No updated payload saved.'
                                }}
                            </div>
                            <dl v-else class="space-y-3">
                                <div
                                    v-for="field in fields"
                                    :key="`after-${field}`"
                                    class="flex items-start justify-between gap-4 border-b border-dashed border-slate-200 pb-3 last:border-b-0 last:pb-0"
                                >
                                    <dt class="text-sm text-slate-500">
                                        {{ fieldLabel(field) }}
                                    </dt>
                                    <dd
                                        class="max-w-[60%] text-right text-sm font-medium text-slate-900"
                                    >
                                        {{
                                            fieldValue(
                                                approval_voucher.after_payload,
                                                field,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                <section class="print-section grid gap-4 xl:grid-cols-2">
                    <div class="rounded-[24px] border border-slate-200 p-5">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Preparer Notes
                        </h2>
                        <p
                            class="mt-3 text-sm leading-6 whitespace-pre-line text-slate-600"
                        >
                            {{
                                approval_voucher.remarks ??
                                'No remarks provided.'
                            }}
                        </p>
                    </div>
                    <div class="rounded-[24px] border border-slate-200 p-5">
                        <h2 class="text-lg font-semibold text-slate-950">
                            Rejection Reason
                        </h2>
                        <p
                            class="mt-3 text-sm leading-6 whitespace-pre-line text-slate-600"
                        >
                            {{
                                approval_voucher.rejection_reason ??
                                'No rejection recorded.'
                            }}
                        </p>
                    </div>
                </section>

                <section
                    class="print-section rounded-[24px] border border-slate-200 p-5"
                >
                    <h2 class="text-lg font-semibold text-slate-950">
                        Approval Memo
                    </h2>
                    <div
                        v-if="approval_voucher.approval_memo"
                        class="mt-4 grid gap-3 sm:grid-cols-3"
                    >
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Memo No.
                            </div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">
                                {{ approval_voucher.approval_memo.memo_no }}
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Status
                            </div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">
                                {{
                                    approval_voucher.approval_memo.status_label
                                }}
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div
                                class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                            >
                                Approved
                            </div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">
                                {{
                                    formatDateTime(
                                        approval_voucher.approval_memo
                                            .approved_at,
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="approval_voucher.approval_memo?.remarks"
                        class="mt-4 text-sm text-slate-600"
                    >
                        {{ approval_voucher.approval_memo.remarks }}
                    </div>
                    <div
                        v-if="!approval_voucher.approval_memo"
                        class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500"
                    >
                        {{
                            approval_voucher.action === 'delete'
                                ? 'Delete requests do not require approval memos.'
                                : 'No approval memo linked to this voucher.'
                        }}
                    </div>
                </section>

                <section
                    v-if="approval_voucher.action !== 'delete'"
                    class="print-section rounded-[24px] border border-slate-200 p-5"
                >
                    <h2 class="text-lg font-semibold text-slate-950">
                        Uploaded Approval Memo PDF
                    </h2>
                    <div
                        v-if="approval_voucher.approval_memo_pdf_attachment"
                        class="mt-4 rounded-2xl border border-slate-200 px-4 py-3"
                    >
                        <div class="text-sm font-semibold text-slate-900">
                            {{
                                approval_voucher.approval_memo_pdf_attachment
                                    .name
                            }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{
                                formatFileSize(
                                    approval_voucher
                                        .approval_memo_pdf_attachment
                                        .size_bytes,
                                )
                            }}
                            -
                            {{
                                approval_voucher
                                    .approval_memo_pdf_attachment.mime_type
                            }}
                            -
                            {{
                                formatDateTime(
                                    approval_voucher
                                        .approval_memo_pdf_attachment
                                        .uploaded_at,
                                )
                            }}
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500"
                    >
                        No approval memo PDF was uploaded to this voucher.
                    </div>
                </section>

                <section
                    class="print-section rounded-[24px] border border-slate-200 p-5"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold text-slate-950"
                            >
                                <Paperclip class="size-4" />
                                Supporting Documents
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{
                                    approval_voucher.attachments.length === 0
                                        ? 'No supporting documents attached.'
                                        : `${approval_voucher.attachments.length} file(s) attached to this voucher.`
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="approval_voucher.attachments.length > 0"
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-for="attachment in approval_voucher.attachments"
                            :key="attachment.id"
                            class="rounded-2xl border border-slate-200 px-4 py-3"
                        >
                            <div class="text-sm font-semibold text-slate-900">
                                {{ attachment.name }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ formatFileSize(attachment.size_bytes) }}
                                Â-
                                {{ attachment.mime_type }}
                                Â-
                                {{ formatDateTime(attachment.uploaded_at) }}
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="print-section rounded-[24px] border border-slate-200 p-5"
                >
                    <h2 class="text-lg font-semibold text-slate-950">
                        Signatories
                    </h2>
                    <div class="mt-6 grid gap-8 md:grid-cols-2">
                        <div class="pt-8">
                            <div class="border-t border-slate-400 pt-3">
                                <div
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        approval_voucher.requested_by_user
                                            ?.name ?? '-'
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-xs tracking-[0.25em] text-slate-500 uppercase"
                                >
                                    Prepared by
                                </div>
                                <div class="mt-2 text-xs text-slate-500">
                                    {{
                                        formatDateTime(
                                            approval_voucher.submitted_at ??
                                                approval_voucher.created_at,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="pt-8">
                            <div class="border-t border-slate-400 pt-3">
                                <div
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        approval_voucher.approved_by_user
                                            ?.name ??
                                        'Pending approver signature'
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-xs tracking-[0.25em] text-slate-500 uppercase"
                                >
                                    Approved by
                                </div>
                                <div class="mt-2 text-xs text-slate-500">
                                    {{
                                        formatDateTime(
                                            approval_voucher.approved_at ??
                                                approval_voucher.rejected_at,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.print-document {
    position: relative;
}

.print-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: 0;
    transform: translate(-50%, -50%) rotate(-24deg);
    font-size: clamp(4rem, 13vw, 8.5rem);
    font-weight: 700;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.16);
    white-space: nowrap;
    user-select: none;
}

@page {
    size: auto;
    margin: 12mm;
}

@media print {
    .print-page {
        min-height: auto;
        background: #fff;
        padding: 0;
    }

    .print-toolbar {
        display: none;
    }

    .print-document {
        max-width: none;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        overflow: visible;
    }

    .print-section,
    .print-section > div {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>
