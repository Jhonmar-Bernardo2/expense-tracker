<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, PiggyBank, Plus, Tags, Trash2 } from 'lucide-vue-next';
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
import { dashboard } from '@/routes/app';
import { index as budgets } from '@/routes/finance/budgets';
import {
    destroy as destroyCategoryBudgetPreset,
    index,
    store as storeCategoryBudgetPreset,
    update as updateCategoryBudgetPreset,
} from '@/routes/finance/category-budget-presets';
import type {
    BreadcrumbItem,
    BudgetAccessShared,
    BudgetPreset,
    BudgetPresetCategoryOption,
} from '@/types';

type PresetFormItem = {
    category_id: number | null;
    amount_limit: string;
};

const props = defineProps<{
    budget_presets: BudgetPreset[];
    categories: BudgetPresetCategoryOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Budget presets', href: index() },
];

const page = usePage();
const budgetAccess = computed(
    () => page.props.budget_access as BudgetAccessShared,
);
const canManageCategoryBudgets = computed(
    () => budgetAccess.value.can_manage_category_budgets,
);

const isPresetDialogOpen = ref(false);
const editingPreset = ref<BudgetPreset | null>(null);
const deletingPresetId = ref<number | null>(null);

const createPresetFormItem = (
    categoryId: number | null = props.categories[0]?.id ?? null,
): PresetFormItem => ({
    category_id: categoryId,
    amount_limit: '',
});

const presetForm = useForm({
    name: '',
    items: [createPresetFormItem()],
});

const allPresets = computed(() => props.budget_presets);
const hasExpenseCategories = computed(() => props.categories.length > 0);
const canCreatePreset = computed(() => hasExpenseCategories.value);
const canAddMorePresetItems = computed(
    () => presetForm.items.length < props.categories.length,
);
const presetAllocationCount = computed(() =>
    allPresets.value.reduce((total, preset) => total + preset.items.length, 0),
);
const coveredCategoryCount = computed(() =>
    new Set(
        allPresets.value.flatMap((preset) =>
            preset.items.map((item) => item.category_id),
        ),
    ).size,
);
const totalPresetAmount = computed(() =>
    allPresets.value.reduce(
        (total, preset) =>
            total +
            preset.items.reduce(
                (presetTotal, item) => presetTotal + item.amount_limit,
                0,
            ),
        0,
    ),
);
const presetListSummary = computed(() => {
    const presetCount = allPresets.value.length.toLocaleString();
    const itemCount = presetAllocationCount.value.toLocaleString();
    const categoryCount = coveredCategoryCount.value.toLocaleString();

    return `${presetCount} preset${
        allPresets.value.length === 1 ? '' : 's'
    } with ${itemCount} saved allocation${
        presetAllocationCount.value === 1 ? '' : 's'
    } across ${categoryCount} categor${
        coveredCategoryCount.value === 1 ? 'y' : 'ies'
    }.`;
});

const presetDialogTitle = computed(() =>
    editingPreset.value === null
        ? 'Create budget preset'
        : 'Edit budget preset',
);
const presetDialogDescription = computed(() =>
    editingPreset.value === null
        ? 'Build one preset with many categories and saved budget amounts.'
        : 'Update this preset and the category amounts saved inside it.',
);
const presetSubmitLabel = computed(() =>
    editingPreset.value === null ? 'Create preset' : 'Save preset',
);

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

const presetTotal = (preset: BudgetPreset) =>
    preset.items.reduce((total, item) => total + item.amount_limit, 0);

const resetPresetForm = () => {
    presetForm.reset();
    presetForm.clearErrors();
    presetForm.name = '';
    presetForm.items = [createPresetFormItem()];
};

const openCreatePresetDialog = () => {
    if (!canCreatePreset.value || !canManageCategoryBudgets.value) {
        return;
    }

    editingPreset.value = null;
    resetPresetForm();
    isPresetDialogOpen.value = true;
};

const openEditPresetDialog = (preset: BudgetPreset) => {
    if (!canManageCategoryBudgets.value) {
        return;
    }

    editingPreset.value = preset;
    presetForm.name = preset.name;
    presetForm.items = preset.items.map((item) => ({
        category_id: item.category_id,
        amount_limit: item.amount_limit.toFixed(2),
    }));
    presetForm.clearErrors();
    isPresetDialogOpen.value = true;
};

const closePresetDialog = () => {
    isPresetDialogOpen.value = false;
    editingPreset.value = null;
    resetPresetForm();
};

const submitPreset = () => {
    if (editingPreset.value === null) {
        presetForm.post(storeCategoryBudgetPreset().url, {
            preserveScroll: true,
            onSuccess: () => closePresetDialog(),
        });

        return;
    }

    presetForm.put(updateCategoryBudgetPreset(editingPreset.value.id).url, {
        preserveScroll: true,
        onSuccess: () => closePresetDialog(),
    });
};

const removePreset = (preset: BudgetPreset) => {
    if (!canManageCategoryBudgets.value || deletingPresetId.value !== null) {
        return;
    }

    if (!window.confirm(`Remove the preset "${preset.name}"?`)) {
        return;
    }

    deletingPresetId.value = preset.id;

    router.delete(destroyCategoryBudgetPreset(preset.id).url, {
        preserveScroll: true,
        onFinish: () => {
            deletingPresetId.value = null;
        },
    });
};

const availablePresetCategories = (index: number) => {
    const selectedCategoryIds = presetForm.items
        .map((item, itemIndex) =>
            itemIndex === index ? null : item.category_id,
        )
        .filter((categoryId): categoryId is number => categoryId !== null);

    const currentCategoryId = presetForm.items[index]?.category_id ?? null;

    return props.categories.filter(
        (category) =>
            category.id === currentCategoryId ||
            !selectedCategoryIds.includes(category.id),
    );
};

const addPresetItem = () => {
    if (!canAddMorePresetItems.value) {
        return;
    }

    const selectedCategoryIds = presetForm.items
        .map((item) => item.category_id)
        .filter((categoryId): categoryId is number => categoryId !== null);
    const nextCategoryId =
        props.categories.find(
            (category) => !selectedCategoryIds.includes(category.id),
        )?.id ?? null;

    presetForm.items.push(createPresetFormItem(nextCategoryId));
};

const removePresetItem = (index: number) => {
    if (presetForm.items.length === 1) {
        return;
    }

    presetForm.items.splice(index, 1);
};
</script>

<template>
    <Head title="Budget presets" />

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
                                <Tags class="size-4 text-muted-foreground" />
                                Budget presets
                            </div>
                            <Badge variant="outline" class="rounded-md font-medium">
                                Reusable category bundles
                            </Badge>
                        </div>

                        <p class="max-w-4xl text-sm leading-6 text-muted-foreground">
                            Create reusable preset bundles here, then choose
                            them from the
                            <span class="font-medium text-foreground">Use preset</span>
                            option when adding category budgets.
                        </p>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline" class="rounded-md font-medium">
                                {{ presetListSummary }}
                            </Badge>
                            <span>
                                Total preset value:
                                {{ formatCurrency(totalPresetAmount) }}
                            </span>
                        </div>

                        <div
                            v-if="!canManageCategoryBudgets"
                            class="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground"
                        >
                            Admins can view presets here, but only the Finance
                            Team can create, edit, and remove them.
                        </div>
                    </div>

                    <ResponsiveActionGroup align="end">
                        <Button
                            variant="outline"
                            class="w-full sm:w-auto"
                            @click="router.get(budgets().url)"
                        >
                            <PiggyBank class="mr-2 size-4" />
                            Open budgets
                        </Button>
                        <Button
                            v-if="canManageCategoryBudgets"
                            class="w-full sm:w-auto"
                            :disabled="!canCreatePreset"
                            @click="openCreatePresetDialog"
                        >
                            <Plus class="mr-2 size-4" />
                            Create preset
                        </Button>
                    </ResponsiveActionGroup>
                </div>

                <div class="grid gap-3 p-4 md:grid-cols-3">
                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Presets
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-foreground">
                            {{ allPresets.length }}
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Saved bundles ready for monthly budget setup.
                        </p>
                    </div>

                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Categories covered
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-foreground">
                            {{ coveredCategoryCount }}
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Expense categories already included in presets.
                        </p>
                    </div>

                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Saved allocations
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-foreground">
                            {{ presetAllocationCount }}
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Category budget rows ready to be applied from a preset.
                        </p>
                    </div>
                </div>
            </section>

            <Dialog
                v-if="canManageCategoryBudgets"
                v-model:open="isPresetDialogOpen"
                @update:open="
                    (open) => {
                        if (!open) {
                            closePresetDialog();
                        }
                    }
                "
            >
                <DialogContent class="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ presetDialogTitle }}</DialogTitle>
                        <DialogDescription>
                            {{ presetDialogDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submitPreset">
                        <div class="grid gap-2">
                            <Label for="preset-name">Preset name</Label>
                            <Input
                                id="preset-name"
                                v-model="presetForm.name"
                                type="text"
                                maxlength="255"
                                placeholder="e.g. Office operations, Travel pack"
                                required
                            />
                            <InputError :message="presetForm.errors.name" />
                        </div>

                        <div class="space-y-3 rounded-xl border bg-muted/20 p-4">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div class="text-sm font-medium text-foreground">
                                        Categories inside this preset
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        One preset can contain many categories.
                                        Each saved amount becomes part of the preset.
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!canAddMorePresetItems"
                                    @click="addPresetItem"
                                >
                                    <Plus class="mr-2 size-4" />
                                    Add category
                                </Button>
                            </div>

                            <InputError :message="presetForm.errors.items" />

                            <div class="space-y-3">
                                <div
                                    v-for="(item, itemIndex) in presetForm.items"
                                    :key="`preset-item-${itemIndex}`"
                                    class="rounded-lg border bg-background p-3"
                                >
                                    <div
                                        class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_auto]"
                                    >
                                        <div class="grid gap-2">
                                            <Label
                                                :for="`preset-item-category-${itemIndex}`"
                                            >
                                                Category
                                            </Label>
                                            <Select
                                                v-model="
                                                    presetForm.items[itemIndex]
                                                        .category_id
                                                "
                                            >
                                                <SelectTrigger
                                                    :id="`preset-item-category-${itemIndex}`"
                                                >
                                                    <SelectValue
                                                        placeholder="Select category"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="category in availablePresetCategories(
                                                            itemIndex,
                                                        )"
                                                        :key="category.id"
                                                        :value="category.id"
                                                    >
                                                        {{ category.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                :message="
                                                    presetForm.errors[
                                                        `items.${itemIndex}.category_id`
                                                    ]
                                                "
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label
                                                :for="`preset-item-amount-${itemIndex}`"
                                            >
                                                Budget
                                            </Label>
                                            <Input
                                                :id="`preset-item-amount-${itemIndex}`"
                                                v-model="
                                                    presetForm.items[itemIndex]
                                                        .amount_limit
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
                                                    presetForm.errors[
                                                        `items.${itemIndex}.amount_limit`
                                                    ]
                                                "
                                            />
                                        </div>

                                        <div class="flex items-end">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="w-full md:w-auto"
                                                :disabled="presetForm.items.length === 1"
                                                @click="removePresetItem(itemIndex)"
                                            >
                                                <Trash2 class="mr-2 size-4" />
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter class="gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                @click="closePresetDialog"
                            >
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="presetForm.processing">
                                <Spinner v-if="presetForm.processing" />
                                {{ presetSubmitLabel }}
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
                            <Tags class="size-4" />
                            Saved presets
                        </div>
                        <div class="text-sm text-muted-foreground">
                            Reusable bundles for the Finance Team's category budget setup.
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div
                        v-if="!hasExpenseCategories"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        No expense categories available yet.
                    </div>

                    <div
                        v-else-if="allPresets.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        No presets saved yet.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="preset in allPresets"
                                :key="`preset-card-${preset.id}`"
                                class="rounded-lg border border-border/70 bg-background p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-foreground">
                                            {{ preset.name }}
                                        </div>
                                        <div class="mt-1 text-sm text-muted-foreground">
                                            {{ preset.items.length }}
                                            {{ preset.items.length === 1 ? 'category' : 'categories' }}
                                            saved
                                        </div>
                                    </div>
                                    <Badge variant="secondary" class="rounded-md px-2 py-0.5">
                                        {{ formatCurrency(presetTotal(preset)) }}
                                    </Badge>
                                </div>

                                <div class="mt-3 space-y-2">
                                    <div
                                        class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                                    >
                                        Categories
                                    </div>
                                    <div
                                        v-for="item in preset.items"
                                        :key="item.id"
                                        class="flex items-center justify-between gap-3 rounded-md border border-border/60 px-3 py-2 text-sm"
                                    >
                                        <span class="truncate text-foreground">
                                            {{ item.category_name ?? 'Category' }}
                                        </span>
                                        <span class="tabular-nums text-muted-foreground">
                                            {{ formatCurrency(item.amount_limit) }}
                                        </span>
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
                                        @click="openEditPresetDialog(preset)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Edit preset
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="deletingPresetId === preset.id"
                                        @click="removePreset(preset)"
                                    >
                                        <Spinner v-if="deletingPresetId === preset.id" />
                                        <Trash2 v-else class="mr-2 size-4" />
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
                                                    Preset
                                                </th>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Categories
                                                </th>
                                                <th
                                                    class="h-11 px-4 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                >
                                                    Total
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
                                                v-for="preset in allPresets"
                                                :key="preset.id"
                                                class="transition-colors hover:bg-muted/30"
                                            >
                                                <td class="px-4 py-3 font-medium tabular-nums">
                                                    {{ preset.name }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="space-y-2">
                                                        <div
                                                            v-for="item in preset.items"
                                                            :key="item.id"
                                                            class="flex items-center justify-between gap-3 rounded-md border border-border/60 px-3 py-2 text-sm"
                                                        >
                                                            <span class="truncate text-foreground">
                                                                {{ item.category_name ?? 'Category' }}
                                                            </span>
                                                            <span class="tabular-nums text-muted-foreground">
                                                                {{ formatCurrency(item.amount_limit) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 font-medium tabular-nums text-foreground">
                                                    {{ formatCurrency(presetTotal(preset)) }}
                                                </td>
                                                <td
                                                    v-if="canManageCategoryBudgets"
                                                    class="px-4 py-3"
                                                >
                                                    <div class="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            title="Edit budget preset"
                                                            @click="openEditPresetDialog(preset)"
                                                        >
                                                            <Pencil class="size-4" />
                                                            <span class="sr-only">
                                                                Edit preset
                                                            </span>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            class="text-muted-foreground hover:text-destructive"
                                                            title="Remove budget preset"
                                                            :disabled="deletingPresetId === preset.id"
                                                            @click="removePreset(preset)"
                                                        >
                                                            <Spinner
                                                                v-if="deletingPresetId === preset.id"
                                                            />
                                                            <Trash2
                                                                v-else
                                                                class="size-4"
                                                            />
                                                            <span class="sr-only">
                                                                Remove preset
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
