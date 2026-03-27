<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck, Clock3, ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import DashboardMetricCard from '@/components/shared/DashboardMetricCard.vue';
import DashboardMetricGrid from '@/components/shared/DashboardMetricGrid.vue';
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
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as notificationsIndex, read as readNotification, readAll as readAllNotifications } from '@/routes/notifications';
import type {
    BreadcrumbItem,
    NotificationItem,
    NotificationsShared,
    Paginator,
} from '@/types';

const props = defineProps<{
    notification_items: Paginator<NotificationItem>;
}>();

const page = usePage();
const sharedNotifications = computed(
    () =>
        (page.props.notifications ?? {
            unread_count: 0,
        }) as NotificationsShared,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Notifications', href: notificationsIndex() },
];
const summaryMetrics = computed(() => [
    {
        id: 'notifications-unread',
        label: 'Unread',
        value: sharedNotifications.value.unread_count.toLocaleString(),
        helper: 'Notifications that still need attention.',
        icon: Bell,
        tone: sharedNotifications.value.unread_count > 0 ? ('warning' as const) : ('success' as const),
    },
    {
        id: 'notifications-visible',
        label: 'Visible items',
        value: props.notification_items.meta.total.toLocaleString(),
        helper: 'Items available in your current inbox view.',
        icon: CheckCheck,
        tone: 'info' as const,
    },
    {
        id: 'notifications-latest-update',
        label: 'Latest update',
        value: formatDateTime(props.notification_items.data[0]?.created_at ?? null),
        helper: 'Newest notification visible on this page.',
        icon: Clock3,
        tone: 'default' as const,
    },
]);

const markAsRead = (notification: NotificationItem) => {
    router.patch(readNotification(notification.id).url, {}, { preserveScroll: true });
};

const markAllAsRead = () => {
    router.patch(readAllNotifications().url, {}, { preserveScroll: true });
};

const formatDateTime = (value: string | null) => {
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
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1.5">
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <Bell class="size-5" />
                            Notifications
                        </CardTitle>
                        <CardDescription>
                            Review approval workflow alerts and mark them as read
                            once handled.
                        </CardDescription>
                    </div>

                    <ResponsiveActionGroup align="end">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="sharedNotifications.unread_count === 0"
                            @click="markAllAsRead"
                        >
                            <CheckCheck class="mr-2 size-4" />
                            Mark all as read
                        </Button>
                    </ResponsiveActionGroup>
                </CardHeader>
                <CardContent>
                    <DashboardMetricGrid>
                        <DashboardMetricCard
                            v-for="metric in summaryMetrics"
                            :key="metric.id"
                            :label="metric.label"
                            :value="metric.value"
                            :helper="metric.helper"
                            :icon="metric.icon"
                            :tone="metric.tone"
                        />
                    </DashboardMetricGrid>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>Inbox</CardTitle>
                    <CardDescription>
                        {{ notification_items.meta.total }} notification{{
                            notification_items.meta.total === 1 ? '' : 's'
                        }}
                        available.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="notification_items.data.length === 0"
                        class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No notifications yet.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="notification in notification_items.data"
                            :key="notification.id"
                            class="rounded-xl border p-4 shadow-sm transition-colors"
                            :class="
                                notification.is_read
                                    ? 'bg-background'
                                    : 'border-primary/30 bg-primary/5'
                            "
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge
                                            :variant="
                                                notification.is_read
                                                    ? 'outline'
                                                    : 'default'
                                            "
                                        >
                                            {{
                                                notification.is_read
                                                    ? 'Read'
                                                    : 'Unread'
                                            }}
                                        </Badge>
                                        <span class="text-xs text-muted-foreground">
                                            {{ formatDateTime(notification.created_at) }}
                                        </span>
                                    </div>
                                    <div>
                                        <h2 class="font-semibold text-foreground">
                                            {{ notification.title }}
                                        </h2>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{ notification.body }}
                                        </p>
                                    </div>
                                </div>

                                <ResponsiveActionGroup align="end">
                                    <Button
                                        v-if="!notification.is_read"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="markAsRead(notification)"
                                    >
                                        Mark read
                                    </Button>
                                    <Button
                                        v-if="notification.href"
                                        as-child
                                        size="sm"
                                    >
                                        <Link :href="notification.href">
                                            <ExternalLink class="mr-2 size-4" />
                                            Open request
                                        </Link>
                                    </Button>
                                </ResponsiveActionGroup>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <Pagination v-if="notification_items.meta.last_page > 1">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrev
                                        :href="notification_items.links.prev"
                                        :disabled="!notification_items.links.prev"
                                    />
                                </PaginationItem>
                                <PaginationItem
                                    v-for="link in notification_items.meta.links"
                                    :key="link.label"
                                >
                                    <PaginationLink
                                        v-if="
                                            link.label !== 'Previous' &&
                                            link.label !== 'Next'
                                        "
                                        :href="link.url"
                                        :is-active="link.active"
                                        :disabled="!link.url"
                                    >
                                        <span v-html="link.label" />
                                    </PaginationLink>
                                </PaginationItem>
                                <PaginationItem>
                                    <PaginationNext
                                        :href="notification_items.links.next"
                                        :disabled="!notification_items.links.next"
                                    />
                                </PaginationItem>
                            </PaginationContent>
                        </Pagination>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
