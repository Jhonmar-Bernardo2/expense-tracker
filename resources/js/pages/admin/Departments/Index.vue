<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveActionGroup from '@/components/shared/ResponsiveActionGroup.vue';
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
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { displayDepartmentName } from '@/lib/plain-language';
import { dashboard } from '@/routes/app';
import { destroy, index, store, update } from '@/routes/admin/departments';
import type { BreadcrumbItem, Department } from '@/types';

defineProps<{
    departments: Department[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Departments',
        href: index(),
    },
];

const isDialogOpen = ref(false);
const editingDepartment = ref<Department | null>(null);
const deletingDepartmentId = ref<number | null>(null);

const form = useForm({
    name: '',
    description: '',
});

const dialogTitle = computed(() =>
    editingDepartment.value ? 'Edit department' : 'Create department',
);

const dialogDescription = computed(() =>
    editingDepartment.value
        ? 'Update this department.'
        : 'Add a department for grouping users.',
);

const submitLabel = computed(() =>
    editingDepartment.value ? 'Save changes' : 'Create department',
);

const openCreateDialog = () => {
    editingDepartment.value = null;
    form.reset();
    form.clearErrors();
    isDialogOpen.value = true;
};

const openEditDialog = (department: Department) => {
    editingDepartment.value = department;
    form.name = department.name;
    form.description = department.description ?? '';
    form.clearErrors();
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    editingDepartment.value = null;
    form.reset();
    form.clearErrors();
};

const handleDialogOpenChange = (open: boolean) => {
    isDialogOpen.value = open;

    if (!open) {
        closeDialog();
    }
};

const submit = () => {
    const action = editingDepartment.value
        ? update(editingDepartment.value.id)
        : store();

    form.submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

const deleteDepartment = (department: Department) => {
    if (!department.can_delete || deletingDepartmentId.value !== null) {
        return;
    }

    if (!window.confirm(`Delete the "${department.name}" department?`)) {
        return;
    }

    deletingDepartmentId.value = department.id;

    router.delete(destroy(department.id).url, {
        preserveScroll: true,
        onFinish: () => {
            deletingDepartmentId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Departments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <Building2 class="size-5" />
                            Departments
                        </CardTitle>
                        <CardDescription>
                            Organize users by department.
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
                                New department
                            </Button>
                        </DialogTrigger>

                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                                <DialogDescription>
                                    {{ dialogDescription }}
                                </DialogDescription>
                            </DialogHeader>

                            <form class="space-y-5" @submit.prevent="submit">
                                <div class="grid gap-2">
                                    <Label for="department-name">Name</Label>
                                    <Input
                                        id="department-name"
                                        v-model="form.name"
                                        type="text"
                                        maxlength="255"
                                        placeholder="e.g. Finance, Operations"
                                        autofocus
                                        required
                                    />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="department-description"
                                        >Description</Label
                                    >
                                    <Input
                                        id="department-description"
                                        v-model="form.description"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Short description for this department"
                                    />
                                    <InputError
                                        :message="form.errors.description"
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
                    <CardTitle>Department list</CardTitle>
                    <CardDescription>
                        {{ departments.length }} department{{
                            departments.length === 1 ? '' : 's'
                        }}
                        available.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="departments.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No departments found yet.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 md:hidden">
                            <div
                                v-for="department in departments"
                                :key="`department-card-${department.id}`"
                                class="rounded-xl border p-4 shadow-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <div
                                                class="font-medium text-foreground"
                                            >
                                                {{
                                                    displayDepartmentName(
                                                        department,
                                                        department.name,
                                                    )
                                                }}
                                            </div>
                                            <span
                                                v-if="department.is_locked"
                                                class="rounded-full border px-2 py-0.5 text-xs text-muted-foreground"
                                            >
                                                Protected
                                            </span>
                                        </div>
                                        <div
                                            class="mt-2 text-sm text-muted-foreground"
                                        >
                                            {{
                                                department.description ||
                                                'No description'
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Assigned users
                                        </div>
                                        <div class="mt-1 font-medium">
                                            {{ department.user_count }}
                                        </div>
                                    </div>
                                    <div v-if="department.is_locked">
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Note
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Central budget department
                                        </div>
                                    </div>
                                </div>

                                <ResponsiveActionGroup class="mt-4" align="end">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="department.is_locked"
                                        @click="openEditDialog(department)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            department.is_locked ||
                                            !department.can_delete ||
                                            deletingDepartmentId ===
                                                department.id
                                        "
                                        @click="deleteDepartment(department)"
                                    >
                                        <Spinner
                                            v-if="
                                                deletingDepartmentId ===
                                                department.id
                                            "
                                        />
                                        <Trash2 v-else class="mr-2 size-4" />
                                        Delete
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>

                        <div
                            class="hidden overflow-hidden rounded-lg border md:block"
                        >
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
                                                Description
                                            </th>
                                            <th class="px-4 py-3 font-medium">
                                                Assigned users
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
                                            v-for="department in departments"
                                            :key="department.id"
                                        >
                                            <td
                                                class="px-4 py-3 font-medium text-foreground"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span>{{
                                                        displayDepartmentName(
                                                            department,
                                                            department.name,
                                                        )
                                                    }}</span>
                                                    <span
                                                        v-if="
                                                            department.is_locked
                                                        "
                                                        class="rounded-full border px-2 py-0.5 text-xs text-muted-foreground"
                                                    >
                                                        Protected
                                                    </span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-muted-foreground"
                                            >
                                                {{
                                                    department.description ||
                                                    'No description'
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-muted-foreground"
                                            >
                                                {{ department.user_count }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <ResponsiveActionGroup
                                                    align="end"
                                                    :full-width-on-mobile="
                                                        false
                                                    "
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        :disabled="
                                                            department.is_locked
                                                        "
                                                        @click="
                                                            openEditDialog(
                                                                department,
                                                            )
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
                                                            department.is_locked ||
                                                            !department.can_delete ||
                                                            deletingDepartmentId ===
                                                                department.id
                                                        "
                                                        @click="
                                                            deleteDepartment(
                                                                department,
                                                            )
                                                        "
                                                    >
                                                        <Spinner
                                                            v-if="
                                                                deletingDepartmentId ===
                                                                department.id
                                                            "
                                                        />
                                                        <Trash2
                                                            v-else
                                                            class="mr-2 size-4"
                                                        />
                                                        Delete
                                                    </Button>
                                                </ResponsiveActionGroup>
                                                <p
                                                    v-if="department.is_locked"
                                                    class="mt-2 text-right text-xs text-muted-foreground"
                                                >
                                                    Central budget department
                                                </p>
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
