<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index, store, update } from '@/routes/categories';
import type { BreadcrumbItem, Category, CategoryType } from '@/types';

type CategoryOption = {
    value: CategoryType;
    label: string;
};

const props = defineProps<{
    categories: Category[];
    filters: {
        type: CategoryType | null;
    };
    types: CategoryOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Categories',
        href: index(),
    },
];

const isDialogOpen = ref(false);
const editingCategory = ref<Category | null>(null);
const deletingCategoryId = ref<number | null>(null);
const selectedTypeFilter = ref<CategoryType | 'all'>(
    props.filters.type ?? 'all',
);

const form = useForm({
    name: '',
    type: 'expense' as CategoryType,
});

const dialogTitle = computed(() =>
    editingCategory.value ? 'Edit category' : 'Create category',
);

const dialogDescription = computed(() =>
    editingCategory.value
        ? 'Update the shared category details used across departments.'
        : 'Create a shared category for income or expense tracking.',
);

const submitLabel = computed(() =>
    editingCategory.value ? 'Save changes' : 'Create category',
);

const openCreateDialog = () => {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
    form.type =
        selectedTypeFilter.value === 'all'
            ? 'expense'
            : selectedTypeFilter.value;
    isDialogOpen.value = true;
};

const openEditDialog = (category: Category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.type = category.type;
    form.clearErrors();
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
};

const handleDialogOpenChange = (open: boolean) => {
    isDialogOpen.value = open;

    if (!open) {
        editingCategory.value = null;
        form.reset();
        form.clearErrors();
    }
};

const submit = () => {
    const submittedType = form.type;
    const action = editingCategory.value
        ? update(editingCategory.value.id)
        : store();

    form.submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => {
            closeDialog();

            if (
                selectedTypeFilter.value !== 'all' &&
                selectedTypeFilter.value !== submittedType
            ) {
                applyTypeFilter(submittedType);
            }
        },
    });
};

const applyTypeFilter = (value: CategoryType | 'all') => {
    selectedTypeFilter.value = value;

    router.get(
        index.url({
            query: {
                type: value === 'all' ? undefined : value,
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

const deleteCategory = (category: Category) => {
    if (!category.can_delete || deletingCategoryId.value !== null) {
        return;
    }

    if (!window.confirm(`Delete the "${category.name}" category?`)) {
        return;
    }

    const action = destroy(category.id);

    deletingCategoryId.value = category.id;

    router.delete(action.url, {
        preserveScroll: true,
        onFinish: () => {
            deletingCategoryId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Tags class="size-5" />
                                Category management
                            </CardTitle>
                            <CardDescription>
                                Manage the shared category list used across all
                                departments.
                            </CardDescription>
                        </div>

                        <Dialog
                            :open="isDialogOpen"
                            @update:open="handleDialogOpenChange"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    class="w-full sm:w-auto"
                                    @click="openCreateDialog"
                                >
                                    <Plus class="mr-2 size-4" />
                                    New category
                                </Button>
                            </DialogTrigger>

                            <DialogContent class="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                                    <DialogDescription>
                                        {{ dialogDescription }}
                                    </DialogDescription>
                                </DialogHeader>

                                <form
                                    class="space-y-5"
                                    @submit.prevent="submit"
                                >
                                    <div class="grid gap-2">
                                        <Label for="category-name">Name</Label>
                                        <Input
                                            id="category-name"
                                            v-model="form.name"
                                            type="text"
                                            placeholder="e.g. Salary, Food, Rent"
                                            autofocus
                                            maxlength="255"
                                            required
                                        />
                                        <InputError
                                            :message="form.errors.name"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="category-type">Type</Label>
                                        <Select v-model="form.type">
                                            <SelectTrigger
                                                id="category-type"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select a type"
                                                />
                                            </SelectTrigger>
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

                                    <DialogFooter class="gap-2 sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            @click="closeDialog"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            :disabled="form.processing"
                                        >
                                            <Spinner v-if="form.processing" />
                                            {{ submitLabel }}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                </Card>

                <Card class="border-sidebar-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                        <CardDescription>
                            Narrow the list by category type.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Label for="type-filter">Type</Label>
                        <Select
                            :model-value="selectedTypeFilter"
                            @update:model-value="
                                applyTypeFilter($event as CategoryType | 'all')
                            "
                        >
                            <SelectTrigger id="type-filter" class="w-full">
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem
                                    v-for="type in types"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Categories</CardTitle>
                    <CardDescription>
                        {{ categories.length }}
                        {{
                            categories.length === 1 ? 'category' : 'categories'
                        }}
                        available.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="categories.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No categories found for the current filter.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="category in categories"
                                :key="`category-card-${category.id}`"
                                class="rounded-xl border p-4 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium text-foreground">
                                            {{ category.name }}
                                        </div>
                                        <div class="mt-2">
                                            <Badge
                                                :variant="
                                                    category.type === 'income'
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                                class="capitalize"
                                            >
                                                {{ category.type }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <div class="text-xs text-muted-foreground">
                                            Usage
                                        </div>
                                        <div class="mt-1 text-sm text-muted-foreground">
                                            {{ category.transaction_count }}
                                            transactions
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-muted-foreground">
                                            Budget usage
                                        </div>
                                        <div class="mt-1 text-sm text-muted-foreground">
                                            {{ category.budget_count }} budgets
                                        </div>
                                    </div>
                                </div>

                                <ResponsiveActionGroup class="mt-4" align="end">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(category)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            !category.can_delete ||
                                            deletingCategoryId === category.id
                                        "
                                        @click="deleteCategory(category)"
                                    >
                                        <Spinner
                                            v-if="
                                                deletingCategoryId ===
                                                category.id
                                            "
                                        />
                                        <Trash2 v-else class="mr-2 size-4" />
                                        Delete
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div class="hidden overflow-hidden rounded-lg border md:block">
                            <div class="overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-border text-sm"
                            >
                                <thead
                                    class="bg-muted/50 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            Name
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Type
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Usage
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
                                        v-for="category in categories"
                                        :key="category.id"
                                    >
                                        <td
                                            class="px-4 py-3 font-medium text-foreground"
                                        >
                                            {{ category.name }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    category.type === 'income'
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                                class="capitalize"
                                            >
                                                {{ category.type }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ category.transaction_count }}
                                            transactions
                                            <span class="mx-1">&middot;</span>
                                            {{ category.budget_count }} budgets
                                        </td>
                                        <td class="px-4 py-3">
                                            <ResponsiveActionGroup
                                                align="end"
                                                :full-width-on-mobile="false"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        openEditDialog(category)
                                                    "
                                                >
                                                    <Pencil
                                                        class="mr-2 size-4"
                                                    />
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    :disabled="
                                                        !category.can_delete ||
                                                        deletingCategoryId ===
                                                            category.id
                                                    "
                                                    @click="
                                                        deleteCategory(category)
                                                    "
                                                >
                                                    <Spinner
                                                        v-if="
                                                            deletingCategoryId ===
                                                            category.id
                                                        "
                                                    />
                                                    <Trash2
                                                        v-else
                                                        class="mr-2 size-4"
                                                    />
                                                    Delete
                                                </Button>
                                            </ResponsiveActionGroup>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
