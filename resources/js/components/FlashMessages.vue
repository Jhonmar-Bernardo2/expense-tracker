<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type FlashProps = {
    success?: string | null;
    error?: string | null;
    status?: string | null;
};

const page = usePage();

const flash = computed(() => (page.props.flash ?? {}) as FlashProps);

const messages = computed(() => {
    const items: Array<{
        key: keyof FlashProps;
        title: string;
        message: string;
        variant?: 'default' | 'destructive';
    }> = [];

    if (flash.value.error) {
        items.push({
            key: 'error',
            title: 'Error',
            message: flash.value.error,
            variant: 'destructive',
        });
    }

    if (flash.value.success) {
        items.push({
            key: 'success',
            title: 'Success',
            message: flash.value.success,
            variant: 'default',
        });
    }

    if (flash.value.status) {
        if (flash.value.status === 'verification-link-sent') {
            return items;
        }

        items.push({
            key: 'status',
            title: 'Info',
            message: flash.value.status,
            variant: 'default',
        });
    }

    return items;
});
</script>

<template>
    <div v-if="messages.length" class="space-y-3">
        <Alert
            v-for="item in messages"
            :key="item.key"
            :variant="item.variant"
            :class="
                item.key === 'success'
                    ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-50'
                    : ''
            "
        >
            <AlertTitle>{{ item.title }}</AlertTitle>
            <AlertDescription class="text-sm">
                {{ item.message }}
            </AlertDescription>
        </Alert>
    </div>
</template>
