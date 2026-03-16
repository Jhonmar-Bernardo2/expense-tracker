<script setup lang="ts">
import { computed } from 'vue';

type BarSeries = {
    name: string;
    values: number[];
    color?: string;
};

const props = defineProps<{
    labels: string[];
    series: BarSeries[];
}>();

const palette = ['#0ea5e9', '#f97316', '#22c55e', '#e11d48'];

const normalized = computed(() => {
    const labels = props.labels ?? [];
    const series = (props.series ?? []).map((s, index) => ({
        ...s,
        color: s.color ?? palette[index % palette.length],
        values: (s.values ?? []).slice(0, labels.length).map((v) => (Number.isFinite(v) ? v : 0)),
    }));

    const max = Math.max(0, ...series.flatMap((s) => s.values));

    return { labels, series, max };
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
                    <line x1="40" y1="20" x2="40" y2="220" stroke="currentColor" opacity="0.15" />
                    <line x1="40" y1="220" x2="700" y2="220" stroke="currentColor" opacity="0.15" />

                    <g v-for="(label, i) in normalized.labels" :key="label">
                        <text :x="60 + i * (620 / Math.max(1, normalized.labels.length - 1))" y="248" text-anchor="middle" class="fill-muted-foreground" style="font-size: 11px;">
                            {{ label }}
                        </text>
                    </g>

                    <g v-for="(label, i) in normalized.labels" :key="label + '-bars'">
                        <g v-for="(serie, sIdx) in normalized.series" :key="serie.name" :transform="`translate(${60 + i * (620 / normalized.labels.length)}, 0)`">
                            <rect
                                :x="sIdx * 18"
                                :y="220 - (normalized.max === 0 ? 0 : (serie.values[i] / normalized.max) * 180)"
                                width="14"
                                :height="normalized.max === 0 ? 0 : (serie.values[i] / normalized.max) * 180"
                                :fill="serie.color"
                                rx="3"
                            />
                        </g>
                    </g>
                </g>
            </svg>
        </div>

        <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
            <div v-for="serie in normalized.series" :key="serie.name" class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded" :style="{ backgroundColor: serie.color }" />
                <span>{{ serie.name }}</span>
            </div>
        </div>
    </div>
</template>