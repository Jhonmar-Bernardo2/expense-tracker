<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        helper?: string | null;
        icon?: Component | null;
        tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
        class?: string;
    }>(),
    {
        helper: null,
        icon: null,
        tone: 'default',
        class: undefined,
    },
);

const toneClasses = computed(() =>
    ({
        default: 'border-border bg-muted/20',
        success: 'border-emerald-500/20 bg-emerald-500/5',
        warning: 'border-amber-500/20 bg-amber-500/5',
        danger: 'border-destructive/20 bg-destructive/5',
        info: 'border-sky-500/20 bg-sky-500/5',
    })[props.tone],
);

const iconClasses = computed(() =>
    ({
        default: 'text-muted-foreground',
        success: 'text-emerald-600',
        warning: 'text-amber-600',
        danger: 'text-destructive',
        info: 'text-sky-600',
    })[props.tone],
);
</script>

<template>
    <div
        :class="
            cn(
                'min-w-0 rounded-lg border p-4 shadow-sm',
                toneClasses,
                props.class,
            )
        "
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 space-y-3">
                <div
                    class="max-w-[13rem] text-xs leading-relaxed text-muted-foreground"
                >
                    {{ label }}
                </div>
                <div
                    class="min-w-0 text-xl font-semibold leading-tight tracking-tight text-foreground tabular-nums md:text-2xl"
                >
                    {{ value }}
                </div>
            </div>
            <component
                :is="icon"
                v-if="icon"
                :class="cn('mt-0.5 size-4 shrink-0', iconClasses)"
            />
        </div>
        <p
            v-if="helper"
            class="mt-3 max-w-[18rem] text-xs leading-relaxed text-muted-foreground"
        >
            {{ helper }}
        </p>
    </div>
</template>
