<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import PaginationLink from './PaginationLink.vue';

withDefaults(
    defineProps<{
        href?: string | null;
        disabled?: boolean;
    }>(),
    {
        href: null,
        disabled: false,
    },
);
</script>

<template>
    <PaginationLink :href="href" :disabled="disabled">
        <span class="sr-only">Next</span>
        <ChevronRight class="size-4" />
    </PaginationLink>
</template>