<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Send, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { approve as approveVoucher, index as approvalVoucherIndex, reject as rejectVoucher, submit as submitVoucher, update as updateVoucher } from '@/routes/approval-vouchers';
import type { ApprovalVoucher, ApprovalVoucherPayload, BreadcrumbItem, Category, CategoryType, DepartmentOption } from '@/types';

type TransactionTypeOption = { value: CategoryType; label: string };

const props = defineProps<{
    approval_voucher: ApprovalVoucher;
    categories: Category[];
    departments: DepartmentOption[];
    transaction_types: TransactionTypeOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Approval Vouchers', href: approvalVoucherIndex() },
    { title: props.approval_voucher.voucher_no, href: approvalVoucherIndex() },
];

const payload = computed(
    () => props.approval_voucher.after_payload ?? props.approval_voucher.before_payload,
);
const isTransaction = computed(() => props.approval_voucher.module === 'transaction');
const isDelete = computed(() => props.approval_voucher.action === 'delete');
const canEdit = computed(() => props.approval_voucher.permissions.can_edit);

const form = useForm({
    module: props.approval_voucher.module,
    action: props.approval_voucher.action,
    target_id: props.approval_voucher.target_id,
    department_id: payload.value?.department_id ?? props.approval_voucher.department_id,
    type:
        payload.value && 'type' in payload.value
            ? (payload.value.type as CategoryType)
            : ('expense' as CategoryType),
    category_id: payload.value?.category_id ?? null,
    title: payload.value && 'title' in payload.value ? payload.value.title : '',
    amount: payload.value && 'amount' in payload.value ? String(payload.value.amount) : '',
    description:
        payload.value && 'description' in payload.value
            ? (payload.value.description ?? '')
            : '',
    transaction_date:
        payload.value && 'transaction_date' in payload.value
            ? (payload.value.transaction_date ?? '')
            : '',
    month: payload.value && 'month' in payload.value ? payload.value.month : 1,
    year: payload.value && 'year' in payload.value ? payload.value.year : new Date().getFullYear(),
    amount_limit:
        payload.value && 'amount_limit' in payload.value
            ? String(payload.value.amount_limit)
            : '',
    remarks: props.approval_voucher.remarks ?? '',
});

const approveForm = useForm({ remarks: props.approval_voucher.remarks ?? '' });
const rejectForm = useForm({
    rejection_reason: props.approval_voucher.rejection_reason ?? '',
});

const formCategories = computed(() =>
    props.categories.filter((category) =>
        isTransaction.value ? category.type === form.type : category.type === 'expense',
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

const nameForDepartment = (id: number | null | undefined) =>
    props.departments.find((department) => department.id === id)?.name ??
    props.approval_voucher.department?.name ??
    '-';
const nameForCategory = (id: number | null | undefined) =>
    props.categories.find((category) => category.id === id)?.name ?? '-';
const monthLabel = (month: number | null | undefined) =>
    month && month >= 1 && month <= 12
        ? new Date(2000, month - 1, 1).toLocaleString('en-US', { month: 'long' })
        : '-';
const fieldValue = (data: ApprovalVoucherPayload, key: string) => {
    if (!data) {
        return '-';
    }

    if (key === 'department_id') {
        return nameForDepartment(data.department_id);
    }

    if (key === 'category_id') {
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
        ? ['department_id', 'type', 'category_id', 'title', 'amount', 'transaction_date', 'description']
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
        amount_limit: 'Monthly limit',
    })[key] ?? key;

const saveDraft = () =>
    form.put(updateVoucher(props.approval_voucher.id).url, { preserveScroll: true });
const submitRequest = () =>
    router.post(submitVoucher(props.approval_voucher.id).url, {}, { preserveScroll: true });
const approveRequest = () =>
    approveForm.patch(approveVoucher(props.approval_voucher.id).url, { preserveScroll: true });
const rejectRequest = () =>
    rejectForm.patch(rejectVoucher(props.approval_voucher.id).url, { preserveScroll: true });
</script>

<template>
    <Head :title="approval_voucher.voucher_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <Button as-child variant="ghost" size="sm" class="w-fit px-0">
                        <Link :href="approvalVoucherIndex()"><ArrowLeft class="mr-2 size-4" />Back to queue</Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold">{{ approval_voucher.voucher_no }}</h1>
                        <p class="text-sm text-muted-foreground">{{ approval_voucher.subject }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge>{{ approval_voucher.status_label }}</Badge>
                        <Badge variant="outline">{{ approval_voucher.module_label }}</Badge>
                        <Badge variant="outline">{{ approval_voucher.action_label }}</Badge>
                    </div>
                </div>
                <div class="space-y-1 text-sm text-muted-foreground">
                    <div><span class="font-medium text-foreground">Requester:</span> {{ approval_voucher.requested_by_user?.name ?? '-' }}</div>
                    <div><span class="font-medium text-foreground">Department:</span> {{ approval_voucher.department?.name ?? '-' }}</div>
                    <div><span class="font-medium text-foreground">Submitted:</span> {{ approval_voucher.submitted_at ?? '-' }}</div>
                    <div><span class="font-medium text-foreground">Applied:</span> {{ approval_voucher.applied_at ?? '-' }}</div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Payload</CardTitle>
                        <CardDescription>Review the before and after snapshots for this request.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-2 rounded-lg border p-4">
                            <div class="text-sm font-medium">Before</div>
                            <div v-if="approval_voucher.before_payload === null" class="text-sm text-muted-foreground">No prior final record snapshot.</div>
                            <div v-else class="space-y-2 text-sm">
                                <div v-for="field in fields" :key="`before-${field}`" class="flex justify-between gap-4">
                                    <span class="text-muted-foreground">{{ fieldLabel(field) }}</span>
                                    <span class="text-right font-medium">{{ fieldValue(approval_voucher.before_payload, field) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 rounded-lg border p-4">
                            <div class="text-sm font-medium">After</div>
                            <div v-if="approval_voucher.after_payload === null" class="text-sm text-muted-foreground">
                                {{ isDelete ? 'Approval will void or archive the final record.' : 'No updated payload saved.' }}
                            </div>
                            <div v-else class="space-y-2 text-sm">
                                <div v-for="field in fields" :key="`after-${field}`" class="flex justify-between gap-4">
                                    <span class="text-muted-foreground">{{ fieldLabel(field) }}</span>
                                    <span class="text-right font-medium">{{ fieldValue(approval_voucher.after_payload, field) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium">Requester notes</div>
                            <p class="mt-2 text-sm text-muted-foreground">{{ approval_voucher.remarks ?? 'No remarks provided.' }}</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium">Rejection reason</div>
                            <p class="mt-2 text-sm text-muted-foreground">{{ approval_voucher.rejection_reason ?? 'No rejection recorded.' }}</p>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4">
                    <Card v-if="canEdit" class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Update Draft</CardTitle>
                            <CardDescription>Adjust the request before you submit it.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="saveDraft">
                                <div v-if="!isDelete" class="grid gap-2">
                                    <Label>Department</Label>
                                    <Select v-model="form.department_id">
                                        <SelectTrigger><SelectValue placeholder="Select department" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.department_id" />
                                </div>
                                <template v-if="isTransaction && !isDelete">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label>Type</Label>
                                            <Select v-model="form.type">
                                                <SelectTrigger><SelectValue placeholder="Select type" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="type in transaction_types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError :message="form.errors.type" />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Category</Label>
                                            <Select v-model="form.category_id">
                                                <SelectTrigger><SelectValue placeholder="Select category" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="category in formCategories" :key="category.id" :value="category.id">{{ category.name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError :message="form.errors.category_id" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2"><Label>Title</Label><Input v-model="form.title" type="text" /><InputError :message="form.errors.title" /></div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2"><Label>Amount</Label><Input v-model="form.amount" type="number" min="0.01" step="0.01" /><InputError :message="form.errors.amount" /></div>
                                        <div class="grid gap-2"><Label>Date</Label><Input v-model="form.transaction_date" type="date" /><InputError :message="form.errors.transaction_date" /></div>
                                    </div>
                                    <div class="grid gap-2"><Label>Description</Label><Input v-model="form.description" type="text" /><InputError :message="form.errors.description" /></div>
                                </template>
                                <template v-if="!isTransaction && !isDelete">
                                    <div class="grid gap-2">
                                        <Label>Category</Label>
                                        <Select v-model="form.category_id">
                                            <SelectTrigger><SelectValue placeholder="Select category" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="category in formCategories" :key="category.id" :value="category.id">{{ category.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.category_id" />
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="grid gap-2"><Label>Month</Label><Select v-model="form.month"><SelectTrigger><SelectValue placeholder="Select month" /></SelectTrigger><SelectContent><SelectItem v-for="month in 12" :key="month" :value="month">{{ monthLabel(month) }}</SelectItem></SelectContent></Select><InputError :message="form.errors.month" /></div>
                                        <div class="grid gap-2"><Label>Year</Label><Input v-model="form.year" type="number" min="1900" max="2100" /><InputError :message="form.errors.year" /></div>
                                    </div>
                                    <div class="grid gap-2"><Label>Monthly limit</Label><Input v-model="form.amount_limit" type="number" min="0.01" step="0.01" /><InputError :message="form.errors.amount_limit" /></div>
                                </template>
                                <div class="grid gap-2">
                                    <Label>Remarks</Label>
                                    <textarea v-model="form.remarks" class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring" />
                                    <InputError :message="form.errors.remarks" />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button type="submit" :disabled="form.processing"><Spinner v-if="form.processing" />Save draft</Button>
                                    <Button v-if="approval_voucher.permissions.can_submit" type="button" variant="outline" @click="submitRequest"><Send class="mr-2 size-4" />Submit</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card v-if="approval_voucher.permissions.can_approve || approval_voucher.permissions.can_reject" class="border-sidebar-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Admin Review</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="approval_voucher.permissions.can_approve" class="space-y-2">
                                <Label>Approval notes</Label>
                                <textarea v-model="approveForm.remarks" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring" />
                                <InputError :message="approveForm.errors.remarks" />
                                <Button :disabled="approveForm.processing" @click="approveRequest"><Spinner v-if="approveForm.processing" /><Check class="mr-2 size-4" />Approve and apply</Button>
                            </div>
                            <div v-if="approval_voucher.permissions.can_reject" class="space-y-2">
                                <Label>Rejection reason</Label>
                                <textarea v-model="rejectForm.rejection_reason" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring" />
                                <InputError :message="rejectForm.errors.rejection_reason" />
                                <Button variant="destructive" :disabled="rejectForm.processing" @click="rejectRequest"><Spinner v-if="rejectForm.processing" /><X class="mr-2 size-4" />Reject</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
