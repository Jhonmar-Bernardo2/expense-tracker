<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, FileText, Plus, ReceiptText, RotateCcw, SendHorizontal, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import * as voucherRoutes from '@/routes/vouchers';
import voucherLiquidationRoutes from '@/routes/vouchers/liquidation';
import type { BreadcrumbItem, Voucher } from '@/types';

type ExpenseCategoryOption = { id: number; name: string };
type LiquidationDraftItem = { category_id: number | null; description: string; amount: string; expense_date: string };

const props = defineProps<{ voucher: Voucher; expense_categories: ExpenseCategoryOption[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Cash Vouchers', href: voucherRoutes.index() },
    { title: props.voucher.voucher_no, href: voucherRoutes.show(props.voucher.id) },
];

const today = new Date().toISOString().slice(0, 10);
const newItem = (): LiquidationDraftItem => ({
    category_id: props.expense_categories[0]?.id ?? null,
    description: '',
    amount: '',
    expense_date: today,
});

const approvalForm = useForm({
    approved_amount: props.voucher.approved_amount ?? props.voucher.requested_amount ?? '',
    liquidation_due_date: props.voucher.liquidation_due_date ?? '',
    remarks: '',
});
const rejectForm = useForm({ remarks: '' });
const releaseForm = useForm({
    released_amount: props.voucher.released_amount ?? props.voucher.approved_amount ?? props.voucher.requested_amount ?? '',
    remarks: '',
});
const liquidationForm = useForm({
    remarks: '',
    items: props.voucher.items?.length
        ? props.voucher.items.map((item) => ({
              category_id: item.category_id,
              description: item.description,
              amount: item.amount ?? '',
              expense_date: item.expense_date ?? today,
          }))
        : [newItem()],
    attachments: [] as File[],
});
const returnForm = useForm({ remarks: props.voucher.liquidation_return_reason ?? '' });
const approveLiquidationForm = useForm({ remarks: '' });

const statusVariant = computed(() => {
    switch (props.voucher.status) {
        case 'approved':
        case 'released':
        case 'liquidation_approved':
            return 'default';
        case 'rejected':
            return 'destructive';
        case 'pending_approval':
        case 'liquidation_submitted':
            return 'secondary';
        default:
            return 'outline';
    }
});

const money = (value: string | null) => Number(value ?? 0).toFixed(2);
const itemError = (index: number, field: 'category_id' | 'description' | 'amount' | 'expense_date') =>
    liquidationForm.errors[`items.${index}.${field}`];

const addItem = () => liquidationForm.items.push(newItem());
const removeItem = (index: number) => {
    if (liquidationForm.items.length > 1) {
        liquidationForm.items.splice(index, 1);
    }
};
const onAttachmentsChange = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    liquidationForm.attachments = files ? Array.from(files) : [];
};

const approveVoucher = () => {
    const action = voucherRoutes.approve(props.voucher.id);
    approvalForm.submit(action.method, action.url, { preserveScroll: true });
};
const rejectVoucher = () => {
    const action = voucherRoutes.reject(props.voucher.id);
    rejectForm.submit(action.method, action.url, { preserveScroll: true });
};
const releaseVoucher = () => {
    const action = voucherRoutes.release(props.voucher.id);
    releaseForm.submit(action.method, action.url, { preserveScroll: true });
};
const submitLiquidation = () => {
    const action = voucherLiquidationRoutes.submit(props.voucher.id);
    liquidationForm.submit(action.method, action.url, { preserveScroll: true, forceFormData: true });
};
const returnLiquidation = () => {
    const action = voucherLiquidationRoutes.return(props.voucher.id);
    returnForm.submit(action.method, action.url, { preserveScroll: true });
};
const approveLiquidation = () => {
    const action = voucherLiquidationRoutes.approve(props.voucher.id);
    approveLiquidationForm.submit(action.method, action.url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="voucher.voucher_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <CardTitle class="flex items-center gap-2 text-xl"><FileText class="size-5" />{{ voucher.voucher_no }}</CardTitle>
                        <CardDescription>{{ voucher.purpose }}</CardDescription>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="statusVariant">{{ voucher.status_label }}</Badge>
                        <Badge variant="outline">{{ voucher.type_label }}</Badge>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Requested</p><p class="mt-2 text-2xl font-semibold">{{ money(voucher.requested_amount) }}</p></div>
                    <div class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Approved</p><p class="mt-2 text-2xl font-semibold">{{ money(voucher.approved_amount) }}</p></div>
                    <div class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Released</p><p class="mt-2 text-2xl font-semibold">{{ money(voucher.released_amount) }}</p></div>
                    <div class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Liquidated</p><p class="mt-2 text-2xl font-semibold">{{ money(voucher.liquidation_total) }}</p></div>
                </CardContent>
            </Card>

            <div v-if="voucher.rejection_reason" class="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm">
                <span class="font-medium">Rejection reason:</span> {{ voucher.rejection_reason }}
            </div>
            <div v-if="voucher.liquidation_return_reason" class="rounded-lg border border-amber-400/40 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <span class="font-medium">Liquidation returned:</span> {{ voucher.liquidation_return_reason }}
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Voucher details</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div><p class="text-xs text-muted-foreground">Department</p><p class="mt-1 font-medium">{{ voucher.department?.name ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Requested by</p><p class="mt-1 font-medium">{{ voucher.requested_by?.name ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Approved by</p><p class="mt-1 font-medium">{{ voucher.approved_by?.name ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Released by</p><p class="mt-1 font-medium">{{ voucher.released_by?.name ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Due date</p><p class="mt-1 font-medium">{{ voucher.liquidation_due_date ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Submitted</p><p class="mt-1 font-medium">{{ voucher.submitted_at ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Liquidation reviewed by</p><p class="mt-1 font-medium">{{ voucher.liquidation_reviewed_by?.name ?? '-' }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Posted at</p><p class="mt-1 font-medium">{{ voucher.posted_at ?? '-' }}</p></div>
                </CardContent>
            </Card>

            <Card v-if="voucher.permissions.can_approve || voucher.permissions.can_reject || voucher.permissions.can_release" class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Approval actions</CardTitle><CardDescription>Review and move the voucher to the next stage.</CardDescription></CardHeader>
                <CardContent class="grid gap-6 lg:grid-cols-3">
                    <form v-if="voucher.permissions.can_approve" class="space-y-3 rounded-lg border p-4" @submit.prevent="approveVoucher">
                        <div class="flex items-center gap-2 font-medium"><CheckCircle2 class="size-4" />Approve request</div>
                        <div class="grid gap-2"><Label for="approve-amount">Approved amount</Label><Input id="approve-amount" v-model="approvalForm.approved_amount" type="number" min="0.01" step="0.01" /><InputError :message="approvalForm.errors.approved_amount" /></div>
                        <div class="grid gap-2"><Label for="approve-due-date">Liquidation due date</Label><Input id="approve-due-date" v-model="approvalForm.liquidation_due_date" type="date" /><InputError :message="approvalForm.errors.liquidation_due_date" /></div>
                        <div class="grid gap-2"><Label for="approve-remarks">Remarks</Label><textarea id="approve-remarks" v-model="approvalForm.remarks" rows="3" class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" /></div>
                        <Button type="submit" :disabled="approvalForm.processing"><Spinner v-if="approvalForm.processing" />Approve</Button>
                    </form>

                    <form v-if="voucher.permissions.can_reject" class="space-y-3 rounded-lg border p-4" @submit.prevent="rejectVoucher">
                        <div class="flex items-center gap-2 font-medium"><XCircle class="size-4" />Reject request</div>
                        <div class="grid gap-2"><Label for="reject-remarks">Reason</Label><textarea id="reject-remarks" v-model="rejectForm.remarks" rows="5" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" /><InputError :message="rejectForm.errors.remarks" /></div>
                        <Button type="submit" variant="destructive" :disabled="rejectForm.processing"><Spinner v-if="rejectForm.processing" />Reject</Button>
                    </form>

                    <form v-if="voucher.permissions.can_release" class="space-y-3 rounded-lg border p-4" @submit.prevent="releaseVoucher">
                        <div class="flex items-center gap-2 font-medium"><SendHorizontal class="size-4" />Release funds</div>
                        <div class="grid gap-2"><Label for="release-amount">Released amount</Label><Input id="release-amount" v-model="releaseForm.released_amount" type="number" min="0.01" step="0.01" /><InputError :message="releaseForm.errors.released_amount" /></div>
                        <div class="grid gap-2"><Label for="release-remarks">Remarks</Label><textarea id="release-remarks" v-model="releaseForm.remarks" rows="3" class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" /></div>
                        <Button type="submit" :disabled="releaseForm.processing"><Spinner v-if="releaseForm.processing" />Release</Button>
                    </form>
                </CardContent>
            </Card>

            <Card v-if="voucher.permissions.can_submit_liquidation" class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Submit liquidation</CardTitle><CardDescription>Add the actual expense breakdown and supporting attachments.</CardDescription></CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submitLiquidation">
                        <div class="space-y-4">
                            <div v-for="(item, index) in liquidationForm.items" :key="index" class="grid gap-3 rounded-lg border p-4 md:grid-cols-[180px_minmax(0,1fr)_140px_160px_auto]">
                                <div class="grid gap-2">
                                    <Label :for="`item-category-${index}`">Category</Label>
                                    <select :id="`item-category-${index}`" v-model="item.category_id" class="h-10 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring">
                                        <option v-for="category in expense_categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                    </select>
                                    <InputError :message="itemError(index, 'category_id')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`item-description-${index}`">Description</Label>
                                    <Input :id="`item-description-${index}`" v-model="item.description" type="text" />
                                    <InputError :message="itemError(index, 'description')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`item-amount-${index}`">Amount</Label>
                                    <Input :id="`item-amount-${index}`" v-model="item.amount" type="number" min="0.01" step="0.01" />
                                    <InputError :message="itemError(index, 'amount')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`item-date-${index}`">Expense date</Label>
                                    <Input :id="`item-date-${index}`" v-model="item.expense_date" type="date" />
                                    <InputError :message="itemError(index, 'expense_date')" />
                                </div>
                                <div class="flex items-end">
                                    <Button type="button" variant="outline" @click="removeItem(index)">Remove</Button>
                                </div>
                            </div>
                            <Button type="button" variant="outline" @click="addItem"><Plus class="mr-2 size-4" />Add item</Button>
                            <InputError :message="liquidationForm.errors.items" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="liquidation-attachments">Attachments</Label>
                            <input id="liquidation-attachments" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm" @change="onAttachmentsChange" />
                            <InputError :message="liquidationForm.errors.attachments" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="liquidation-remarks">Remarks</Label>
                            <textarea id="liquidation-remarks" v-model="liquidationForm.remarks" rows="4" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" />
                        </div>

                        <Button type="submit" :disabled="liquidationForm.processing"><Spinner v-if="liquidationForm.processing" />Submit liquidation</Button>
                    </form>
                </CardContent>
            </Card>

            <Card v-if="voucher.permissions.can_return_liquidation || voucher.permissions.can_approve_liquidation" class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Liquidation review</CardTitle><CardDescription>Review the submitted liquidation before it posts to transactions.</CardDescription></CardHeader>
                <CardContent class="grid gap-6 lg:grid-cols-2">
                    <form v-if="voucher.permissions.can_return_liquidation" class="space-y-3 rounded-lg border p-4" @submit.prevent="returnLiquidation">
                        <div class="flex items-center gap-2 font-medium"><RotateCcw class="size-4" />Return for correction</div>
                        <div class="grid gap-2"><Label for="return-remarks">Reason</Label><textarea id="return-remarks" v-model="returnForm.remarks" rows="5" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" /><InputError :message="returnForm.errors.remarks" /></div>
                        <Button type="submit" variant="outline" :disabled="returnForm.processing"><Spinner v-if="returnForm.processing" />Return liquidation</Button>
                    </form>

                    <form v-if="voucher.permissions.can_approve_liquidation" class="space-y-3 rounded-lg border p-4" @submit.prevent="approveLiquidation">
                        <div class="flex items-center gap-2 font-medium"><ReceiptText class="size-4" />Approve and post</div>
                        <div class="grid gap-2"><Label for="approve-liquidation-remarks">Remarks</Label><textarea id="approve-liquidation-remarks" v-model="approveLiquidationForm.remarks" rows="5" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring" /></div>
                        <Button type="submit" :disabled="approveLiquidationForm.processing"><Spinner v-if="approveLiquidationForm.processing" />Approve liquidation</Button>
                    </form>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Liquidation items</CardTitle></CardHeader>
                <CardContent>
                    <div v-if="!voucher.items?.length" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">No liquidation items submitted yet.</div>
                    <div v-else class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50"><TableRow><TableHead>Date</TableHead><TableHead>Category</TableHead><TableHead>Description</TableHead><TableHead class="text-right">Amount</TableHead></TableRow></TableHeader>
                            <TableBody>
                                <TableRow v-for="item in voucher.items" :key="item.id">
                                    <TableCell>{{ item.expense_date ?? '-' }}</TableCell>
                                    <TableCell>{{ item.category?.name ?? '-' }}</TableCell>
                                    <TableCell>{{ item.description }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ money(item.amount) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Attachments</CardTitle></CardHeader>
                <CardContent>
                    <div v-if="!voucher.attachments?.length" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">No attachments uploaded yet.</div>
                    <div v-else class="space-y-3">
                        <div v-for="attachment in voucher.attachments" :key="attachment.id" class="flex flex-col justify-between gap-3 rounded-lg border p-4 sm:flex-row sm:items-center">
                            <div><p class="font-medium">{{ attachment.original_name }}</p><p class="text-xs text-muted-foreground">{{ attachment.mime_type ?? 'File' }} • {{ attachment.uploaded_by?.name ?? 'Unknown uploader' }}</p></div>
                            <a :href="attachment.download_url" class="text-sm font-medium text-primary underline underline-offset-4">Download</a>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="voucher.transactions?.length" class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Posted transactions</CardTitle><CardDescription>These were created only after liquidation approval.</CardDescription></CardHeader>
                <CardContent>
                    <div class="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader class="bg-muted/50"><TableRow><TableHead>Date</TableHead><TableHead>Category</TableHead><TableHead>Title</TableHead><TableHead class="text-right">Amount</TableHead></TableRow></TableHeader>
                            <TableBody>
                                <TableRow v-for="transaction in voucher.transactions" :key="transaction.id">
                                    <TableCell>{{ transaction.transaction_date ?? '-' }}</TableCell>
                                    <TableCell>{{ transaction.category?.name ?? '-' }}</TableCell>
                                    <TableCell>{{ transaction.title }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ money(transaction.amount) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader><CardTitle>Audit log</CardTitle></CardHeader>
                <CardContent>
                    <div v-if="!voucher.logs?.length" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">No audit activity yet.</div>
                    <div v-else class="space-y-3">
                        <div v-for="log in voucher.logs" :key="log.id" class="rounded-lg border p-4">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-medium">{{ log.action_label }}</p>
                                <p class="text-xs text-muted-foreground">{{ log.created_at ?? '-' }}</p>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ log.user?.name ?? 'System' }}</p>
                            <p v-if="log.from_status_label || log.to_status_label" class="mt-2 text-sm">
                                {{ log.from_status_label ?? 'Start' }} -> {{ log.to_status_label ?? 'Unchanged' }}
                            </p>
                            <p v-if="log.remarks" class="mt-2 text-sm text-muted-foreground">{{ log.remarks }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
