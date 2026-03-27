<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { notifyPrintComplete } from '@/lib/print';
import {
    index as approvalVoucherIndex,
    show as approvalVoucherShow,
} from '@/routes/app/approval-vouchers';
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
const isAllocation = computed(
    () => props.approval_voucher.module === 'allocation',
);
const isDelete = computed(() => props.approval_voucher.action === 'delete');
const hasPreparerNotes = computed(() =>
    Boolean(props.approval_voucher.remarks?.trim()),
);
const hasRejectionReason = computed(() =>
    Boolean(props.approval_voucher.rejection_reason?.trim()),
);
const actionSummary = computed(() => {
    if (props.approval_voucher.module === 'allocation') {
        return props.approval_voucher.action === 'delete'
            ? 'Allocation removal request'
            : 'Monthly allocation request';
    }

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
        : isAllocation.value
          ? ['department_id', 'month', 'year', 'amount_limit']
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
        return 'document-status-approved';
    }

    if (status === 'pending_approval') {
        return 'document-status-pending';
    }

    if (status === 'rejected') {
        return 'document-status-rejected';
    }

    return 'document-status-default';
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

    if (key === 'category_id' && 'category_id' in data) {
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

onMounted(() => {
    const searchParams = new URLSearchParams(window.location.search);

    if (searchParams.get('auto_print') !== '1') {
        return;
    }

    const handleAfterPrint = () => {
        window.removeEventListener('afterprint', handleAfterPrint);
        notifyPrintComplete();
    };

    window.addEventListener('afterprint', handleAfterPrint);

    window.setTimeout(() => {
        printDocument();
    }, 200);
});
</script>

<template>
    <Head :title="`${approval_voucher.voucher_no} Print`" />

    <div class="print-page">
        <div class="print-toolbar">
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

        <article class="document-sheet">
            <header class="document-header">
                <div class="document-brand-block">
                    <div class="document-brand">Expense Tracker</div>
                    <div class="document-brand-subtitle">
                        Official approval copy
                    </div>
                </div>

                <div class="document-header-grid">
                    <div>
                        <h1 class="document-title">Approval Voucher</h1>
                        <p class="document-subject">
                            {{
                                approval_voucher.subject ||
                                'No subject provided.'
                            }}
                        </p>
                    </div>

                    <div class="document-reference">
                        <div
                            class="document-status"
                            :class="statusTone(approval_voucher.status)"
                        >
                            {{ approval_voucher.status_label }}
                        </div>
                        <div class="document-reference-label">Voucher No.</div>
                        <div class="document-reference-value">
                            {{ approval_voucher.voucher_no }}
                        </div>
                    </div>
                </div>

                <div class="document-meta-strip">
                    <div class="document-meta-item">
                        <div class="document-meta-label">Module</div>
                        <div class="document-meta-value">
                            {{ approval_voucher.module_label }}
                        </div>
                    </div>
                    <div class="document-meta-item">
                        <div class="document-meta-label">Action</div>
                        <div class="document-meta-value">
                            {{ approval_voucher.action_label }}
                        </div>
                    </div>
                    <div class="document-meta-item">
                        <div class="document-meta-label">Department</div>
                        <div class="document-meta-value">
                            {{
                                approval_voucher.department?.name ??
                                nameForDepartment(currentPayload?.department_id)
                            }}
                        </div>
                    </div>
                    <div class="document-meta-item">
                        <div class="document-meta-label">Prepared By</div>
                        <div class="document-meta-value">
                            {{
                                approval_voucher.requested_by_user?.name ?? '-'
                            }}
                        </div>
                    </div>
                    <div class="document-meta-item">
                        <div class="document-meta-label">Prepared On</div>
                        <div class="document-meta-value">
                            {{
                                formatDateTime(
                                    approval_voucher.submitted_at ??
                                        approval_voucher.created_at,
                                )
                            }}
                        </div>
                    </div>
                </div>
            </header>

            <div class="document-body">
                <section class="document-section">
                    <div class="section-lead">
                        <div class="section-kicker">Voucher Overview</div>
                        <h2 class="section-title">Request Summary</h2>
                    </div>

                    <div class="section-grid section-grid-summary">
                        <section class="panel panel-muted summary-panel">
                            <div class="panel-header">
                                <h3 class="panel-title">Request Information</h3>
                            </div>
                            <div class="panel-body">
                                <table class="info-table">
                                    <tbody>
                                        <tr>
                                            <th>Module</th>
                                            <td>
                                                {{
                                                    approval_voucher.module_label
                                                }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Action</th>
                                            <td>
                                                <div>
                                                    {{
                                                        approval_voucher.action_label
                                                    }}
                                                </div>
                                                <div class="value-support">
                                                    {{ actionSummary }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Department</th>
                                            <td>
                                                {{
                                                    approval_voucher.department
                                                        ?.name ??
                                                    nameForDepartment(
                                                        currentPayload?.department_id,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Preparer</th>
                                            <td>
                                                <div>
                                                    {{
                                                        approval_voucher
                                                            .requested_by_user
                                                            ?.name ?? '-'
                                                    }}
                                                </div>
                                                <div class="value-support">
                                                    {{
                                                        approval_voucher
                                                            .requested_by_user
                                                            ?.email ??
                                                        'No email available'
                                                    }}
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="panel panel-muted summary-panel">
                            <div class="panel-header">
                                <h3 class="panel-title">Processing Timeline</h3>
                            </div>
                            <div class="panel-body">
                                <table class="info-table">
                                    <tbody>
                                        <tr>
                                            <th>Created</th>
                                            <td>
                                                {{
                                                    formatDateTime(
                                                        approval_voucher.created_at,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Submitted</th>
                                            <td>
                                                {{
                                                    formatDateTime(
                                                        approval_voucher.submitted_at,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Approved</th>
                                            <td>
                                                {{
                                                    formatDateTime(
                                                        approval_voucher.approved_at,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Applied</th>
                                            <td>
                                                {{
                                                    formatDateTime(
                                                        approval_voucher.applied_at,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>

                <section class="document-section document-section-comparison">
                    <div class="section-lead">
                        <div class="section-kicker">Record Comparison</div>
                        <h2 class="section-title">
                            Current and Requested Values
                        </h2>
                    </div>

                    <div class="section-grid section-grid-details">
                        <section class="panel compare-panel">
                            <div class="panel-header">
                                <h3 class="panel-title">Current Details</h3>
                                <p class="panel-description">
                                    Represents the existing record values before
                                    the request is processed.
                                </p>
                            </div>
                            <div class="panel-body">
                                <div
                                    v-if="
                                        approval_voucher.before_payload === null
                                    "
                                    class="empty-state"
                                >
                                    No prior final record snapshot.
                                </div>
                                <table v-else class="compare-table">
                                    <tbody>
                                        <tr
                                            v-for="field in fields"
                                            :key="`before-${field}`"
                                        >
                                            <th>{{ fieldLabel(field) }}</th>
                                            <td>
                                                {{
                                                    fieldValue(
                                                        approval_voucher.before_payload,
                                                        field,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="panel compare-panel">
                            <div class="panel-header">
                                <h3 class="panel-title">Updated Details</h3>
                                <p class="panel-description">
                                    Represents the requested changes to be
                                    applied upon approval.
                                </p>
                            </div>
                            <div class="panel-body">
                                <div
                                    v-if="
                                        approval_voucher.after_payload === null
                                    "
                                    class="empty-state"
                                >
                                    {{
                                        isDelete
                                            ? 'Approval will void or archive the final record.'
                                            : 'No updated payload saved.'
                                    }}
                                </div>
                                <table v-else class="compare-table">
                                    <tbody>
                                        <tr
                                            v-for="field in fields"
                                            :key="`after-${field}`"
                                        >
                                            <th>{{ fieldLabel(field) }}</th>
                                            <td>
                                                {{
                                                    fieldValue(
                                                        approval_voucher.after_payload,
                                                        field,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>

                <section
                    v-if="hasPreparerNotes || hasRejectionReason"
                    class="document-section"
                >
                    <div class="section-lead">
                        <div class="section-kicker">Review Notes</div>
                        <h2 class="section-title">Supporting Remarks</h2>
                    </div>

                    <div class="section-grid section-grid-notes">
                        <section v-if="hasPreparerNotes" class="note-panel">
                            <h3 class="panel-title">Preparer Notes</h3>
                            <p class="note-copy">
                                {{ approval_voucher.remarks }}
                            </p>
                        </section>

                        <section v-if="hasRejectionReason" class="note-panel">
                            <h3 class="panel-title">Rejection Reason</h3>
                            <p class="note-copy">
                                {{ approval_voucher.rejection_reason }}
                            </p>
                        </section>
                    </div>
                </section>

                <section class="document-section document-section-signatures">
                    <div class="section-lead">
                        <div class="section-kicker">Authorization</div>
                        <h2 class="section-title">Signatories</h2>
                    </div>

                    <div class="signature-grid">
                        <div class="signature-block">
                            <div class="signature-line">
                                <div class="signature-name">
                                    {{
                                        approval_voucher.requested_by_user
                                            ?.name ?? '-'
                                    }}
                                </div>
                                <div class="signature-role">Prepared by</div>
                                <div class="signature-date">
                                    {{
                                        formatDateTime(
                                            approval_voucher.submitted_at ??
                                                approval_voucher.created_at,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="signature-block">
                            <div class="signature-line">
                                <div class="signature-name">
                                    {{
                                        approval_voucher.approved_by_user
                                            ?.name ??
                                        'Pending approver signature'
                                    }}
                                </div>
                                <div class="signature-role">Approved by</div>
                                <div class="signature-date">
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
        </article>
    </div>
</template>

<style scoped>
.print-page {
    min-height: 100vh;
    padding: 18px 14px 28px;
    background:
        linear-gradient(180deg, #cbd5e1 0%, #e2e8f0 30%, #cbd5e1 100%);
}

.print-toolbar,
.document-sheet {
    width: min(210mm, 100%);
    margin: 0 auto;
}

.print-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.document-sheet {
    --document-ink: #0f172a;
    --document-muted: #475569;
    --document-muted-soft: #64748b;
    --document-line: #cbd5e1;
    --document-line-soft: #e2e8f0;
    --document-line-strong: #94a3b8;
    --document-surface: #f8fafc;
    --document-surface-muted: #f1f5f9;
    --document-surface-strong: #e2e8f0;
    min-height: 297mm;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    background: #fff;
    border: 1.5px solid var(--document-line-strong);
    box-shadow: 0 26px 64px rgba(15, 23, 42, 0.14);
    color: var(--document-ink);
}

.document-header {
    padding: 16px 18px 12px;
    border-bottom: 1.5px solid var(--document-line-strong);
    background:
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.document-brand-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.document-brand {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: var(--document-muted-soft);
}

.document-brand-subtitle {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--document-muted);
}

.document-header-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    align-items: start;
    margin-top: 10px;
}

.document-title {
    font-size: 31px;
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.02em;
}

.document-subject {
    max-width: 570px;
    margin-top: 7px;
    font-size: 13.2px;
    line-height: 1.48;
    color: var(--document-muted);
}

.document-reference {
    min-width: 57mm;
    padding-left: 18px;
    border-left: 1px solid var(--document-line);
    text-align: right;
}

.document-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border: 1px solid var(--document-line);
    border-radius: 3px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.24em;
    text-transform: uppercase;
}

.document-status-approved {
    background: #eff6f1;
    border-color: #bfd7c8;
    color: #1f5c43;
}

.document-status-pending {
    background: #fff8eb;
    border-color: #e8d2a3;
    color: #8a6117;
}

.document-status-rejected {
    background: #fef2f2;
    border-color: #efc2c2;
    color: #9f2f2f;
}

.document-status-default {
    background: #f8fafc;
    border-color: var(--document-line);
    color: #334155;
}

.document-reference-label {
    margin-top: 12px;
    font-size: 10px;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: var(--document-muted-soft);
}

.document-reference-value {
    margin-top: 4px;
    font-size: 23px;
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.document-meta-strip {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    margin-top: 12px;
    border: 1px solid var(--document-line-strong);
    background: #fff;
}

.document-meta-item {
    min-height: 56px;
    padding: 8px 10px 9px;
    border-right: 1px solid var(--document-line);
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.document-meta-item:last-child {
    border-right: 0;
}

.document-meta-label {
    font-size: 9.6px;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--document-muted-soft);
}

.document-meta-value {
    margin-top: 5px;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
    color: var(--document-ink);
}

.document-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 8px 18px 12px;
}

.document-section {
    break-inside: avoid;
    page-break-inside: avoid;
    border: 1px solid var(--document-line-strong);
    background: #fff;
}

.section-lead {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 0;
    padding: 8px 12px 7px;
    border-bottom: 1px solid var(--document-line);
    background:
        linear-gradient(180deg, var(--document-surface) 0%, #ffffff 100%);
}

.section-kicker {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--document-muted-soft);
}

.section-title {
    margin-top: 3px;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.2;
}

.section-grid {
    display: grid;
    gap: 8px;
    padding: 9px 10px 10px;
}

.section-grid-summary,
.section-grid-details,
.section-grid-notes,
.signature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.panel,
.note-panel {
    border: 1px solid var(--document-line);
    border-radius: 0;
    background: #fff;
    box-sizing: border-box;
}

.panel-muted,
.note-panel {
    background: var(--document-surface);
}

.panel,
.compare-panel,
.summary-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.panel-header {
    padding: 8px 11px 7px;
    border-bottom: 1px solid var(--document-line);
    background: var(--document-surface);
}

.panel-title {
    font-size: 12.6px;
    font-weight: 700;
    line-height: 1.3;
}

.panel-description {
    margin-top: 3px;
    font-size: 11.5px;
    line-height: 1.4;
    color: var(--document-muted);
}

.panel-body {
    flex: 1;
    padding: 8px 11px 10px;
}

.info-table,
.compare-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table tr:last-child th,
.info-table tr:last-child td,
.compare-table tr:last-child th,
.compare-table tr:last-child td {
    border-bottom: 0;
}

.info-table th,
.info-table td,
.compare-table th,
.compare-table td {
    padding: 6px 0;
    border-bottom: 1px solid var(--document-line-soft);
    vertical-align: top;
}

.info-table th,
.compare-table th {
    width: 42%;
    text-align: left;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--document-muted);
}

.info-table td {
    text-align: right;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--document-ink);
}

.compare-table td {
    text-align: left;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--document-ink);
}

.value-support {
    margin-top: 2px;
    font-size: 10.5px;
    font-weight: 500;
    line-height: 1.4;
    color: var(--document-muted);
}

.summary-panel {
    min-height: 54mm;
}

.document-section-comparison {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.document-section-comparison .section-grid-details {
    flex: 1;
    align-items: stretch;
}

.compare-panel {
    min-height: 99mm;
}

.empty-state {
    min-height: 100%;
    padding: 12px;
    border: 1px dashed var(--document-line-strong);
    background: var(--document-surface-muted);
    font-size: 11.5px;
    line-height: 1.45;
    color: var(--document-muted);
    display: flex;
    align-items: center;
}

.note-panel {
    min-height: 28mm;
    padding: 10px 11px 11px;
}

.note-copy {
    margin-top: 6px;
    font-size: 11.5px;
    line-height: 1.5;
    color: var(--document-muted);
    white-space: pre-line;
}

.section-grid-notes > :only-child {
    grid-column: 1 / -1;
}

.document-section-signatures {
    margin-top: auto;
}

.signature-grid {
    display: grid;
    gap: 14px;
    align-items: end;
    padding: 12px 10px 14px;
}

.signature-block {
    min-height: 39mm;
    display: flex;
    align-items: end;
}

.signature-line {
    width: 100%;
    padding-top: 9px;
    border-top: 1.5px solid var(--document-line-strong);
}

.signature-name {
    font-size: 12.5px;
    font-weight: 700;
}

.signature-role {
    margin-top: 3px;
    font-size: 10.5px;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--document-muted-soft);
}

.signature-date {
    margin-top: 6px;
    font-size: 10.5px;
    line-height: 1.4;
    color: var(--document-muted);
}

@media (max-width: 860px) {
    .document-header-grid,
    .document-meta-strip,
    .section-grid-summary,
    .section-grid-details,
    .section-grid-notes,
    .signature-grid {
        grid-template-columns: 1fr;
    }

    .document-reference {
        min-width: 0;
        padding-left: 0;
        border-left: 0;
        text-align: left;
    }

    .document-meta-item {
        min-height: auto;
        border-right: 0;
        border-bottom: 1px solid var(--document-line);
    }

    .document-meta-item:last-child {
        border-bottom: 0;
    }

    .info-table td,
    .compare-table td {
        text-align: left;
    }
}

@page {
    size: A4 portrait;
    margin: 5mm;
}

@media print {
    :global(html),
    :global(body) {
        background: #fff;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .print-page {
        padding: 0;
        background: #fff;
        min-height: auto;
    }

    .print-toolbar {
        display: none;
    }

    .document-sheet {
        width: auto;
        min-height: 286mm;
        border: 0.35mm solid var(--document-line-strong);
        box-shadow: none;
    }

    .document-header {
        padding: 2.2mm 2.2mm 1.8mm;
    }

    .document-body {
        gap: 1.5mm;
        padding: 1.5mm 1.8mm 1.8mm;
    }

    .section-lead {
        padding: 1.35mm 1.9mm 1.2mm;
    }

    .section-grid {
        gap: 1.35mm;
        padding: 1.35mm 1.45mm 1.55mm;
    }

    .document-header-grid {
        grid-template-columns: minmax(0, 1fr) auto !important;
        gap: 2.8mm;
    }

    .document-meta-strip {
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        margin-top: 1.8mm;
    }

    .document-meta-item {
        min-height: 9.8mm;
        padding: 1.1mm 1.35mm 1.2mm;
    }

    .section-grid-summary,
    .section-grid-details,
    .section-grid-notes,
    .signature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .document-reference {
        min-width: 46mm;
        text-align: right !important;
    }

    .document-title {
        font-size: 1.52rem;
    }

    .document-subject {
        max-width: none;
        margin-top: 0.8mm;
        font-size: 9.4px;
        line-height: 1.24;
    }

    .document-status {
        padding: 1mm 2.5mm;
        font-size: 8px;
    }

    .document-meta-label {
        font-size: 7.4px;
    }

    .document-meta-value {
        margin-top: 0.55mm;
        font-size: 8.6px;
        line-height: 1.16;
    }

    .document-reference-label,
    .section-kicker {
        font-size: 7.9px;
    }

    .document-reference-value {
        margin-top: 0.9mm;
        font-size: 1.2rem;
    }

    .section-title {
        margin-top: 0.55mm;
        font-size: 11.8px;
    }

    .panel-header {
        padding: 1.2mm 1.55mm 1.1mm;
    }

    .panel-body,
    .note-panel {
        padding: 1.3mm 1.55mm 1.45mm;
    }

    .panel-title {
        font-size: 9.9px;
    }

    .panel-description,
    .value-support,
    .note-copy,
    .signature-date {
        font-size: 8.5px;
        line-height: 1.18;
    }

    .info-table th,
    .info-table td,
    .compare-table th,
    .compare-table td {
        padding: 1.08mm 0;
        font-size: 8.7px;
    }

    .info-table td {
        text-align: right !important;
    }

    .compare-table td {
        text-align: left !important;
    }

    .empty-state {
        padding: 1.45mm 1.55mm;
        font-size: 8.5px;
        line-height: 1.18;
    }

    .summary-panel {
        min-height: 31mm;
    }

    .compare-panel {
        min-height: 62mm;
    }

    .note-panel {
        min-height: 16mm;
    }

    .document-subject,
    .panel-description,
    .note-copy,
    .signature-date {
        line-height: 1.2;
    }

    .signature-grid {
        gap: 2mm !important;
        padding: 1.55mm 1.45mm 1.7mm;
    }

    .signature-block {
        min-height: 18mm;
    }

    .signature-line {
        padding-top: 1mm;
    }

    .signature-name {
        font-size: 8.8px;
    }

    .signature-role {
        margin-top: 0.7mm;
        font-size: 7.7px;
    }
}
</style>
