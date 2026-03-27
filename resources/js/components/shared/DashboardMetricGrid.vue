<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: string;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(13rem,1fr))]',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
