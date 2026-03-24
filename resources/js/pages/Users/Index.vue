<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, ShieldCheck, UserCog } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { dashboard } from '@/routes';
import { index, store, update } from '@/routes/users';
import { update as updateStatus } from '@/routes/users/status';
import type {
    BreadcrumbItem,
    DepartmentOption,
    ManagedUser,
    RoleOption,
    UserRole,
} from '@/types';

type UserStatusValue = 'active' | 'inactive';

const props = defineProps<{
    users: ManagedUser[];
    departments: DepartmentOption[];
    roles: RoleOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Manage accounts',
        href: index(),
    },
];

const defaultRole = computed<UserRole>(() => props.roles[0]?.value ?? 'staff');
const defaultDepartmentId = computed<number | null>(
    () => props.departments[0]?.id ?? null,
);

const isDialogOpen = ref(false);
const editingUser = ref<ManagedUser | null>(null);
const togglingUserId = ref<number | null>(null);

const form = useForm({
    name: '',
    email: '',
    role: defaultRole.value,
    department_id: defaultDepartmentId.value,
    password: '',
    password_confirmation: '',
    status: 'active' as UserStatusValue,
});

const dialogTitle = computed(() =>
    editingUser.value ? 'Edit account' : 'Create account',
);

const dialogDescription = computed(() =>
    editingUser.value
        ? 'Update the user role, department, and account status.'
        : 'Create a new admin or staff account for the system.',
);

const submitLabel = computed(() =>
    editingUser.value ? 'Save changes' : 'Create account',
);

const statusError = computed(
    () => (form.errors as Record<string, string | undefined>).is_active,
);

const openCreateDialog = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = defaultRole.value;
    form.department_id = defaultDepartmentId.value;
    form.status = 'active';
    isDialogOpen.value = true;
};

const openEditDialog = (user: ManagedUser) => {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.department_id = user.department?.id ?? defaultDepartmentId.value;
    form.password = '';
    form.password_confirmation = '';
    form.status = user.is_active ? 'active' : 'inactive';
    form.clearErrors();
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = defaultRole.value;
    form.department_id = defaultDepartmentId.value;
    form.status = 'active';
};

const handleDialogOpenChange = (open: boolean) => {
    isDialogOpen.value = open;

    if (!open) {
        closeDialog();
    }
};

const submit = () => {
    if (editingUser.value) {
        const action = update(editingUser.value.id);

        form.transform((data) => ({
            name: data.name,
            email: data.email,
            role: data.role,
            department_id: data.department_id,
            is_active: data.status === 'active',
        })).submit(action.method, action.url, {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });

        return;
    }

    const action = store();

    form.transform((data) => ({
        name: data.name,
        email: data.email,
        role: data.role,
        department_id: data.department_id,
        password: data.password,
        password_confirmation: data.password_confirmation,
    })).submit(action.method, action.url, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

const toggleStatus = (user: ManagedUser) => {
    if (togglingUserId.value !== null) {
        return;
    }

    const nextStatus = !user.is_active;
    const prompt = nextStatus
        ? `Activate the account for "${user.name}"?`
        : `Deactivate the account for "${user.name}"?`;

    if (!window.confirm(prompt)) {
        return;
    }

    togglingUserId.value = user.id;

    router.patch(
        updateStatus(user.id).url,
        {
            is_active: nextStatus,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                togglingUserId.value = null;
            },
        },
    );
};

const roleBadgeVariant = (role: UserRole) =>
    role === 'admin' ? 'default' : 'secondary';
</script>

<template>
    <Head title="Manage accounts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <UserCog class="size-5" />
                            Manage accounts
                        </CardTitle>
                        <CardDescription>
                            Create users, assign departments, and manage admin
                            or staff access.
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
                                New account
                            </Button>
                        </DialogTrigger>

                        <DialogContent class="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                                <DialogDescription>
                                    {{ dialogDescription }}
                                </DialogDescription>
                            </DialogHeader>

                            <form class="space-y-5" @submit.prevent="submit">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="user-name">Name</Label>
                                        <Input
                                            id="user-name"
                                            v-model="form.name"
                                            type="text"
                                            maxlength="255"
                                            placeholder="Full name"
                                            autofocus
                                            required
                                        />
                                        <InputError
                                            :message="form.errors.name"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="user-email">Email</Label>
                                        <Input
                                            id="user-email"
                                            v-model="form.email"
                                            type="email"
                                            maxlength="255"
                                            placeholder="user@example.com"
                                            required
                                        />
                                        <InputError
                                            :message="form.errors.email"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="user-role">Role</Label>
                                        <Select v-model="form.role">
                                            <SelectTrigger
                                                id="user-role"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select a role"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="role in roles"
                                                    :key="role.value"
                                                    :value="role.value"
                                                >
                                                    {{ role.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="form.errors.role"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="user-department"
                                            >Department</Label
                                        >
                                        <Select v-model="form.department_id">
                                            <SelectTrigger
                                                id="user-department"
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
                                </div>

                                <div v-if="editingUser" class="grid gap-2">
                                    <Label for="user-status">Status</Label>
                                    <Select v-model="form.status">
                                        <SelectTrigger
                                            id="user-status"
                                            class="w-full"
                                        >
                                            <SelectValue
                                                placeholder="Select a status"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active"
                                                >Active</SelectItem
                                            >
                                            <SelectItem value="inactive"
                                                >Inactive</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="statusError" />
                                </div>

                                <template v-else>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="user-password"
                                                >Password</Label
                                            >
                                            <Input
                                                id="user-password"
                                                v-model="form.password"
                                                type="password"
                                                required
                                            />
                                            <InputError
                                                :message="form.errors.password"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label
                                                for="user-password-confirmation"
                                                >Confirm password</Label
                                            >
                                            <Input
                                                id="user-password-confirmation"
                                                v-model="
                                                    form.password_confirmation
                                                "
                                                type="password"
                                                required
                                            />
                                        </div>
                                    </div>
                                </template>

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
                    <CardTitle>Accounts</CardTitle>
                    <CardDescription>
                        {{ users.length }} account{{
                            users.length === 1 ? '' : 's'
                        }}
                        configured.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="users.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No user accounts found yet.
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
                                        <th class="px-4 py-3 font-medium">
                                            Name
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Email
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Role
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Department
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Verification
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
                                    <tr v-for="user in users" :key="user.id">
                                        <td
                                            class="px-4 py-3 font-medium text-foreground"
                                        >
                                            {{ user.name }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    roleBadgeVariant(user.role)
                                                "
                                            >
                                                {{ user.role }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{
                                                user.department?.name ??
                                                'Unassigned'
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    user.is_active
                                                        ? 'outline'
                                                        : 'destructive'
                                                "
                                            >
                                                {{
                                                    user.is_active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    user.email_verified_at
                                                        ? 'outline'
                                                        : 'secondary'
                                                "
                                            >
                                                {{
                                                    user.email_verified_at
                                                        ? 'Verified'
                                                        : 'Pending'
                                                }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="
                                                        openEditDialog(user)
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
                                                        togglingUserId ===
                                                        user.id
                                                    "
                                                    @click="toggleStatus(user)"
                                                >
                                                    <Spinner
                                                        v-if="
                                                            togglingUserId ===
                                                            user.id
                                                        "
                                                    />
                                                    <ShieldCheck
                                                        v-else
                                                        class="mr-2 size-4"
                                                    />
                                                    {{
                                                        user.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'
                                                    }}
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
