<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { show as approvalMemoShow } from '@/routes/approval-memos';
import type { ApprovalMemo } from '@/types';

const props = defineProps<{
    approval_memo: ApprovalMemo;
    auto_print: boolean;
}>();

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

const printDocument = () => {
    window.print();
};

onMounted(() => {
    if (!props.auto_print) {
        return;
    }

    window.setTimeout(() => {
        window.print();
    }, 100);
});
</script>

<template>
    <Head :title="`${props.approval_memo.memo_no} Print`" />

    <div class="min-h-screen bg-stone-100 px-4 py-5 text-slate-900 sm:px-6 print:bg-white print:px-0 print:py-0">
        <div class="mx-auto mb-4 flex max-w-[820px] flex-wrap items-center justify-between gap-3 print:hidden">
            <Button as-child variant="ghost" size="sm">
                <Link :href="approvalMemoShow(props.approval_memo.id)">
                    <ArrowLeft class="mr-2 size-4" />
                    Back to memo
                </Link>
            </Button>
            <Button type="button" variant="outline" @click="printDocument">
                <Printer class="mr-2 size-4" />
                Print or Save as PDF
            </Button>
        </div>

        <main class="memo-print-sheet mx-auto max-w-[820px] bg-white p-7 shadow-sm print:max-w-none print:shadow-none">
            <header class="border-b-2 border-slate-900 pb-3">
                <p class="text-[11px] tracking-[0.12em] text-slate-600 uppercase">Approval Memo</p>
                <h1 class="mt-1 text-[24px] font-bold text-slate-950">{{ props.approval_memo.memo_no }}</h1>
                <p class="mt-1 text-[12px] text-slate-700">System-generated approval memo for final request submission.</p>
            </header>

            <section class="mt-4">
                <div class="border border-slate-400 bg-slate-50 px-3 py-2">
                    <p class="text-[10px] text-slate-600 uppercase">Status</p>
                    <p class="mt-1 text-[16px] font-bold text-slate-950">{{ props.approval_memo.status_label }}</p>
                    <p class="mt-1 text-[11px] text-slate-700">Approved at {{ formatDateTime(props.approval_memo.approved_at) }}</p>
                </div>
            </section>

            <section class="mt-4">
                <h2 class="mb-2 text-[13px] font-bold tracking-[0.04em] text-slate-950 uppercase">Request Details</h2>
                <table class="w-full border-collapse text-left text-[12px]">
                    <tbody>
                        <tr>
                            <td class="w-1/2 border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Requester</div>
                                <div class="mt-1 text-[13px] font-bold text-slate-950">{{ props.approval_memo.requested_by_user?.name ?? '-' }}</div>
                                <div class="mt-1 text-[11px] text-slate-700">{{ props.approval_memo.requested_by_user?.email ?? '-' }}</div>
                            </td>
                            <td class="w-1/2 border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Department</div>
                                <div class="mt-1 text-[13px] font-bold text-slate-950">{{ props.approval_memo.department?.name ?? '-' }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Module</div>
                                <div class="mt-1 text-[13px] font-bold text-slate-950">{{ props.approval_memo.module_label }}</div>
                            </td>
                            <td class="border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Action</div>
                                <div class="mt-1 text-[13px] font-bold text-slate-950">{{ props.approval_memo.action_label }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Approved By</div>
                                <div class="mt-1 text-[13px] font-bold text-slate-950">{{ props.approval_memo.approved_by_user?.name ?? '-' }}</div>
                                <div class="mt-1 text-[11px] text-slate-700">{{ props.approval_memo.approved_by_user?.email ?? '-' }}</div>
                            </td>
                            <td class="border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Submitted</div>
                                <div class="mt-1 text-[13px] font-bold text-slate-950">{{ formatDateTime(props.approval_memo.submitted_at) }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mt-4">
                <h2 class="mb-2 text-[13px] font-bold tracking-[0.04em] text-slate-950 uppercase">Remarks</h2>
                <table class="w-full border-collapse text-left text-[12px]">
                    <tbody>
                        <tr>
                            <td class="w-1/2 border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Purpose or Remarks</div>
                                <div class="mt-1 whitespace-pre-line text-[12px] leading-6 text-slate-900">
                                    {{ props.approval_memo.remarks ?? 'No remarks provided.' }}
                                </div>
                            </td>
                            <td class="w-1/2 border border-slate-400 px-3 py-2 align-top">
                                <div class="text-[10px] text-slate-600 uppercase">Admin Remarks</div>
                                <div class="mt-1 whitespace-pre-line text-[12px] leading-6 text-slate-900">
                                    {{ props.approval_memo.admin_remarks ?? 'No admin remarks recorded.' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mt-8">
                <table class="w-full border-collapse text-left">
                    <tbody>
                        <tr>
                            <td class="w-1/2 pr-5 align-top">
                                <div class="border-t border-slate-900 pt-2">
                                    <div class="text-[12px] font-bold text-slate-950">{{ props.approval_memo.requested_by_user?.name ?? '-' }}</div>
                                    <div class="mt-1 text-[11px] text-slate-600">Requested by</div>
                                </div>
                            </td>
                            <td class="w-1/2 pl-5 align-top">
                                <div class="border-t border-slate-900 pt-2">
                                    <div class="text-[12px] font-bold text-slate-950">{{ props.approval_memo.approved_by_user?.name ?? '-' }}</div>
                                    <div class="mt-1 text-[11px] text-slate-600">Approved by</div>
                                    <div class="mt-2 text-[11px] text-slate-600">{{ formatDateTime(props.approval_memo.approved_at) }}</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</template>

<style scoped>
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    html,
    body {
        background: #ffffff;
    }

    body {
        margin: 0;
    }

    .memo-print-sheet {
        padding: 0;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    table,
    tr,
    td,
    section,
    header {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>
