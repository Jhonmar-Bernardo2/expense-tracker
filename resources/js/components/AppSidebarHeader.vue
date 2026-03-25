<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as notificationsIndex } from '@/routes/notifications';
import type { BreadcrumbItem } from '@/types';
import type { NotificationsShared } from '@/types/notifications';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const notifications = computed(
    () =>
        (page.props.notifications ?? {
            unread_count: 0,
        }) as NotificationsShared,
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto">
            <Button as-child variant="ghost" size="icon" class="relative">
                <Link :href="notificationsIndex()">
                    <Bell class="size-5" />
                    <span class="sr-only">Notifications</span>
                    <span
                        v-if="notifications.unread_count > 0"
                        class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[11px] font-semibold text-destructive-foreground"
                    >
                        {{
                            notifications.unread_count > 99
                                ? '99+'
                                : notifications.unread_count
                        }}
                    </span>
                </Link>
            </Button>
        </div>
    </header>
</template>
