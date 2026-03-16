<script setup lang="ts">
import { computed } from 'vue';

type PieItem = {
    label: string;
    value: number;
    color?: string;
};

const props = defineProps<{
    items: PieItem[];
    size?: number;
}>();

const palette = [
    '#0ea5e9',
    '#22c55e',
    '#f97316',
    '#e11d48',
    '#a855f7',
    '#facc15',
    '#14b8a6',
    '#64748b',
];

const normalized = computed(() => {
    const safe = (props.items ?? []).filter((item) => Number.isFinite(item.value) && item.value > 0);
    const total = safe.reduce((acc, item) => acc + item.value, 0);

    return {
        total,
        items: safe.map((item, index) => ({
            ...item,
            color: item.color ?? palette[index % palette.length],
        })),
    };
});

const slices = computed(() => {
    const { total, items } = normalized.value;

    if (total <= 0) {
        return [];
    }

    let angle = -Math.PI / 2;

    return items.map((item) => {
        const sliceAngle = (item.value / total) * Math.PI * 2;
        const start = angle;
        const end = angle + sliceAngle;
        angle = end;

        return {
            ...item,
            start,
            end,
        };
    });
});

const viewBox = computed(() => {
    const size = props.size ?? 220;

    return `0 0 ${size} ${size}`;
});

const toPoint = (angle: number, radius: number, center: number) => {
    return {
        x: center + radius * Math.cos(angle),
        y: center + radius * Math.sin(angle),
    };
};

const arcPath = (start: number, end: number, radius: number, center: number) => {
    const startPoint = toPoint(start, radius, center);
    const endPoint = toPoint(end, radius, center);
    const largeArc = end - start > Math.PI ? 1 : 0;

    return [
        `M ${center} ${center}`,
        `L ${startPoint.x} ${startPoint.y}`,
        `A ${radius} ${radius} 0 ${largeArc} 1 ${endPoint.x} ${endPoint.y}`,
        'Z',
    ].join(' ');
};
</script>

<template>
    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
        <div class="flex items-center justify-center">
            <div v-if="slices.length === 0" class="flex h-[220px] w-[220px] items-center justify-center rounded-full border border-dashed text-sm text-muted-foreground">
                No data
            </div>
            <svg v-else :viewBox="viewBox" class="h-[220px] w-[220px]">
                <g>
                    <path
                        v-for="slice in slices"
                        :key="slice.label"
                        :d="arcPath(slice.start, slice.end, 100, 110)"
                        :fill="slice.color"
                        stroke="white"
                        stroke-width="2"
                    />
                </g>
            </svg>
        </div>

        <div class="space-y-2">
            <div class="text-sm font-medium">Breakdown</div>
            <div v-if="normalized.total <= 0" class="text-sm text-muted-foreground">
                Add transactions to see a breakdown.
            </div>
            <ul v-else class="space-y-2 text-sm">
                <li v-for="(item, idx) in normalized.items" :key="item.label" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded" :style="{ backgroundColor: item.color ?? palette[idx % palette.length] }" />
                        <span class="truncate">{{ item.label }}</span>
                    </div>
                    <span class="tabular-nums text-muted-foreground">{{ item.value.toFixed(2) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>