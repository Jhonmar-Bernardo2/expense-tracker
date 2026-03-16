<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next';
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
        <span class="sr-only">Previous</span>
        <ChevronLeft class="size-4" />
    </PaginationLink>
</template>