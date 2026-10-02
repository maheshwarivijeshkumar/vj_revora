<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * AI lead score with its band (§119).
 *
 * Thresholds are tenant-configurable server-side; the defaults here match the
 * spec and are overridable so this component never becomes the source of
 * truth for banding.
 */
const props = withDefaults(
    defineProps<{
        score: number;
        thresholds?: { medium: number; high: number; veryHigh: number };
    }>(),
    { thresholds: () => ({ medium: 40, high: 70, veryHigh: 90 }) },
);

const band = computed(() => {
    if (props.score >= props.thresholds.veryHigh) return { label: 'Very High', tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' };
    if (props.score >= props.thresholds.high) return { label: 'High', tone: 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300' };
    if (props.score >= props.thresholds.medium) return { label: 'Medium', tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' };
    return { label: 'Low', tone: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' };
});
</script>

<template>
    <span
        :class="cn('inline-flex items-center gap-1.5 rounded-[var(--radius-pill)] px-2.5 py-0.5 text-caption font-medium', band.tone)"
        :title="`Score ${score} — ${band.label}`"
    >
        <span class="font-semibold tabular-nums">{{ score }}</span>
        <span class="opacity-75">{{ band.label }}</span>
    </span>
</template>
