<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        align?: 'start' | 'end';
        fullWidthOnMobile?: boolean;
        class?: string;
    }>(),
    {
        align: 'end',
        fullWidthOnMobile: true,
        class: undefined,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col gap-2 sm:flex-row',
                props.align === 'end'
                    ? 'sm:justify-end'
                    : 'sm:justify-start',
                props.fullWidthOnMobile && '[&>*]:w-full sm:[&>*]:w-auto',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
