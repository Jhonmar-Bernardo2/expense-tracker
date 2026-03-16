<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    labels: string[];
    values: number[];
    color?: string;
}>();

const normalized = computed(() => {
    const labels = props.labels ?? [];
    const values = (props.values ?? []).slice(0, labels.length).map((v) => (Number.isFinite(v) ? v : 0));
    const max = Math.max(0, ...values);

    return { labels, values, max };
});

const points = computed(() => {
    if (normalized.value.labels.length === 0) {
        return '';
    }

    const width = 680;
    const height = 200;
    const left = 20;
    const top = 20;
    const usableWidth = width - left;

    return normalized.value.values
        .map((value, index) => {
            const x = left + (index / Math.max(1, normalized.value.values.length - 1)) * usableWidth;
            const y = top + (normalized.value.max === 0 ? height : height - (value / normalized.value.max) * height);

            return `${x},${y}`;
        })
        .join(' ');
});
</script>

<template>
    <div class="space-y-3">
        <div v-if="normalized.labels.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            No data
        </div>

        <div v-else class="overflow-x-auto">
            <svg viewBox="0 0 720 260" class="min-w-[720px]">
                <g>
                    <line x1="20" y1="220" x2="700" y2="220" stroke="currentColor" opacity="0.15" />
                    <polyline
                        :points="points"
                        fill="none"
                        :stroke="color ?? '#0ea5e9'"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle
                        v-for="(value, idx) in normalized.values"
                        :key="idx"
                        :cx="20 + (idx / Math.max(1, normalized.values.length - 1)) * 680"
                        :cy="20 + (normalized.max === 0 ? 200 : 200 - (value / normalized.max) * 200)"
                        r="3.5"
                        :fill="color ?? '#0ea5e9'"
                    />

                    <g v-for="(label, i) in normalized.labels" :key="label">
                        <text
                            v-if="i === 0 || i === normalized.labels.length - 1 || (normalized.labels.length <= 10 && i % 2 === 0)"
                            :x="20 + (i / Math.max(1, normalized.labels.length - 1)) * 680"
                            y="248"
                            text-anchor="middle"
                            class="fill-muted-foreground"
                            style="font-size: 11px;"
                        >
                            {{ label }}
                        </text>
                    </g>
                </g>
            </svg>
        </div>
    </div>
</template>