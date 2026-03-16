<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        href?: string | null;
        isActive?: boolean;
        disabled?: boolean;
    }>(),
    {
        href: null,
        isActive: false,
        disabled: false,
    },
);

const classes = cn(
    buttonVariants({
        variant: props.isActive ? 'default' : 'outline',
        size: 'icon',
    }),
    'h-9 w-9',
    props.disabled && 'pointer-events-none opacity-50',
);
</script>

<template>
    <span v-if="disabled || !href" :class="classes">
        <slot />
    </span>
    <Link v-else :href="href" preserve-scroll preserve-state :class="classes">
        <slot />
    </Link>
</template>