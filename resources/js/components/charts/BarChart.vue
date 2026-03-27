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
    valueFormat?: 'number' | 'currency';
    currency?: string;
    periodLabel?: string | null;
    emptyStateText?: string;
    showValueScale?: boolean;
}>();

const palette = ['#0ea5e9', '#f97316', '#22c55e', '#e11d48'];
const chartTop = 20;
const chartBottom = 220;
const chartLeft = 56;
const chartRight = 700;
const chartHeight = chartBottom - chartTop;

const formatValue = (value: number, compact = false) => {
    const safeValue = Number.isFinite(value) ? value : 0;

    if (props.valueFormat === 'currency') {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: props.currency ?? 'PHP',
            maximumFractionDigits: compact ? 0 : 2,
            notation: compact ? 'compact' : 'standard',
        }).format(safeValue);
    }

    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: compact ? 0 : 2,
        notation: compact ? 'compact' : 'standard',
    }).format(safeValue);
};

const normalized = computed(() => {
    const labels = props.labels ?? [];
    const series = (props.series ?? []).map((s, index) => ({
        ...s,
        color: s.color ?? palette[index % palette.length],
        values: (s.values ?? [])
            .slice(0, labels.length)
            .map((v) => (Number.isFinite(v) ? v : 0)),
    }));

    const max = Math.max(0, ...series.flatMap((s) => s.values));

    return { labels, series, max };
});

const yTicks = computed(() => {
    if (!props.showValueScale) {
        return [];
    }

    const max = normalized.value.max;

    if (max <= 0) {
        return [
            {
                label: formatValue(0, true),
                y: chartBottom,
            },
        ];
    }

    return [1, 0.5, 0].map((ratio) => ({
        label: formatValue(max * ratio, true),
        y: chartBottom - chartHeight * ratio,
    }));
});
</script>

<template>
    <div class="space-y-3">
        <div v-if="periodLabel" class="flex justify-end">
            <span
                class="rounded-full border px-2.5 py-1 text-[11px] text-muted-foreground"
            >
                {{ periodLabel }}
            </span>
        </div>

        <div
            v-if="normalized.labels.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ emptyStateText ?? 'No data available for this chart yet.' }}
        </div>

        <div v-else class="w-full">
            <svg
                viewBox="0 0 720 260"
                preserveAspectRatio="none"
                class="h-full w-full"
            >
                <g>
                    <line
                        :x1="chartLeft"
                        :y1="chartTop"
                        :x2="chartLeft"
                        :y2="chartBottom"
                        stroke="currentColor"
                        opacity="0.15"
                    />
                    <line
                        :x1="chartLeft"
                        :y1="chartBottom"
                        :x2="chartRight"
                        :y2="chartBottom"
                        stroke="currentColor"
                        opacity="0.15"
                    />

                    <g v-for="tick in yTicks" :key="`${tick.label}-${tick.y}`">
                        <line
                            :x1="chartLeft"
                            :y1="tick.y"
                            :x2="chartRight"
                            :y2="tick.y"
                            stroke="currentColor"
                            opacity="0.08"
                        />
                        <text
                            :x="chartLeft - 8"
                            :y="tick.y + 4"
                            text-anchor="end"
                            class="fill-muted-foreground"
                            style="font-size: 10px"
                        >
                            {{ tick.label }}
                        </text>
                    </g>

                    <g v-for="(label, i) in normalized.labels" :key="label">
                        <text
                            :x="
                                chartLeft +
                                20 +
                                i *
                                    ((chartRight - chartLeft - 20) /
                                        Math.max(
                                            1,
                                            normalized.labels.length - 1,
                                        ))
                            "
                            y="248"
                            text-anchor="middle"
                            class="fill-muted-foreground"
                            style="font-size: 11px"
                        >
                            {{ label }}
                        </text>
                    </g>

                    <g
                        v-for="(label, i) in normalized.labels"
                        :key="`${label}-bars`"
                    >
                        <g
                            v-for="(serie, sIdx) in normalized.series"
                            :key="serie.name"
                            :transform="`translate(${
                                chartLeft +
                                16 +
                                i *
                                    ((chartRight - chartLeft) /
                                        normalized.labels.length)
                            }, 0)`"
                        >
                            <rect
                                :x="sIdx * 18"
                                :y="
                                    chartBottom -
                                    (normalized.max === 0
                                        ? 0
                                        : (serie.values[i] / normalized.max) *
                                          chartHeight)
                                "
                                width="14"
                                :height="
                                    normalized.max === 0
                                        ? 0
                                        : (serie.values[i] / normalized.max) *
                                          chartHeight
                                "
                                :fill="serie.color"
                                rx="3"
                            />
                        </g>
                    </g>
                </g>
            </svg>
        </div>

        <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
            <div
                v-for="serie in normalized.series"
                :key="serie.name"
                class="flex items-center gap-2"
            >
                <span
                    class="h-2.5 w-2.5 rounded"
                    :style="{ backgroundColor: serie.color }"
                />
                <span>{{ serie.name }}</span>
            </div>
        </div>
    </div>
</template>
