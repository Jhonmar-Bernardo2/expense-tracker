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
    valueFormat?: 'number' | 'currency';
    currency?: string;
    periodLabel?: string | null;
    emptyStateText?: string;
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
    const safe = (props.items ?? []).filter(
        (item) => Number.isFinite(item.value) && item.value > 0,
    );
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
const formatValue = (value: number) => {
    const safeValue = Number.isFinite(value) ? value : 0;

    if (props.valueFormat === 'currency') {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: props.currency ?? 'PHP',
            maximumFractionDigits: 2,
        }).format(safeValue);
    }

    return safeValue.toLocaleString(undefined, { maximumFractionDigits: 2 });
};

const formattedTotal = computed(() => formatValue(normalized.value.total));
const centerLabel = computed(() => {
    if (normalized.value.items.length === 1) {
        return normalized.value.items[0].label;
    }

    return 'Total';
});

const chartStyle = computed(() => {
    if (normalized.value.total <= 0) {
        return {};
    }

    let current = 0;
    const segments = normalized.value.items.map((item) => {
        const start = current;
        const slice = (item.value / normalized.value.total) * 100;
        current += slice;

        return `${item.color} ${start.toFixed(2)}% ${current.toFixed(2)}%`;
    });

    return {
        backgroundImage: `conic-gradient(${segments.join(', ')})`,
    };
});

const shareSummary = computed(() => {
    return normalized.value.items.map((item) => ({
        ...item,
        percentage:
            normalized.value.total > 0
                ? (item.value / normalized.value.total) * 100
                : 0,
    }));
});
</script>

<template>
    <div class="grid gap-8 xl:grid-cols-[300px_minmax(0,1fr)] xl:items-center">
        <div class="flex items-center justify-center">
            <div
                v-if="slices.length === 0"
                class="flex h-[240px] w-[240px] items-center justify-center rounded-full border border-dashed px-6 text-center text-sm text-muted-foreground"
            >
                {{ emptyStateText ?? 'No data available for this chart yet.' }}
            </div>
            <div
                v-else
                class="relative flex h-[240px] w-[240px] items-center justify-center rounded-full border border-white/5 shadow-[0_18px_50px_rgba(0,0,0,0.18)]"
                :style="chartStyle"
            >
                <div
                    class="flex h-[118px] w-[118px] flex-col items-center justify-center rounded-full border border-white/10 bg-background text-center shadow-inner"
                >
                    <span
                        class="max-w-[84px] truncate text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                    >
                        {{ centerLabel }}
                    </span>
                    <span
                        class="mt-1 text-base font-semibold text-foreground"
                        >{{ formattedTotal }}</span
                    >
                    <span class="mt-1 text-[11px] text-muted-foreground"
                        >tracked spend</span
                    >
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-medium">Breakdown</div>
                    <div class="text-xs text-muted-foreground">
                        How each category contributes to this month&apos;s total
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span
                        v-if="periodLabel"
                        class="rounded-full border px-2.5 py-1 text-[11px] text-muted-foreground"
                    >
                        {{ periodLabel }}
                    </span>
                    <span
                        v-if="normalized.total > 0"
                        class="rounded-full border px-2.5 py-1 text-[11px] text-muted-foreground"
                    >
                        {{ normalized.items.length }} categories
                    </span>
                </div>
            </div>
            <div
                v-if="normalized.total <= 0"
                class="text-sm text-muted-foreground"
            >
                {{ emptyStateText ?? 'Add transactions to see a breakdown.' }}
            </div>
            <ul v-else class="space-y-3 text-sm">
                <li
                    v-for="(item, idx) in shareSummary"
                    :key="item.label"
                    class="rounded-2xl border bg-muted/10 p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="h-3 w-3 shrink-0 rounded-full ring-4 ring-background"
                                :style="{
                                    backgroundColor:
                                        item.color ??
                                        palette[idx % palette.length],
                                }"
                            />
                            <div class="min-w-0">
                                <div class="truncate font-medium">
                                    {{ item.label }}
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    {{ item.percentage.toFixed(1) }}% of total
                                    spending
                                </div>
                            </div>
                        </div>
                        <div class="min-w-[96px] text-right">
                            <div
                                class="font-medium text-foreground tabular-nums"
                            >
                                {{ formatValue(item.value) }}
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted/70"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${item.percentage}%`,
                                    backgroundColor:
                                        item.color ??
                                        palette[idx % palette.length],
                                }"
                            />
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
