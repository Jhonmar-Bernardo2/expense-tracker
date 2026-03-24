<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    Building2,
    FileText,
    FolderGit2,
    LayoutGrid,
    PiggyBank,
    Receipt,
    Tags,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as approvalVouchers } from '@/routes/approval-vouchers';
import { index as budgets } from '@/routes/budgets';
import { index as categories } from '@/routes/categories';
import { index as departments } from '@/routes/departments';
import { index as reports } from '@/routes/reports';
import { index as transactions } from '@/routes/transactions';
import { index as users } from '@/routes/users';
import { index as vouchers } from '@/routes/vouchers';
import type { NavItem, User } from '@/types';

const page = usePage();
const currentUser = computed(() => page.props.auth.user as User | null);
const isAdmin = computed(() => currentUser.value?.role === 'admin');

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Transactions',
        href: transactions(),
        icon: Receipt,
    },
    {
        title: 'Approval Vouchers',
        href: approvalVouchers(),
        icon: FileText,
    },
    {
        title: 'Cash Vouchers',
        href: vouchers(),
        icon: FileText,
    },
    {
        title: 'Reports',
        href: reports(),
        icon: BarChart3,
    },
    {
        title: 'Budgets',
        href: budgets(),
        icon: PiggyBank,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Manage Accounts',
        href: users(),
        icon: Users,
    },
    {
        title: 'Departments',
        href: departments(),
        icon: Building2,
    },
    {
        title: 'Categories',
        href: categories(),
        icon: Tags,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Platform" />
            <NavMain
                v-if="isAdmin"
                :items="adminNavItems"
                label="Administration"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
