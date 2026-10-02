<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Semantic status badge (§119).
 *
 * Colour never carries meaning alone — the label is always rendered, so the
 * badge stays readable for colour-blind users and in greyscale print.
 */

export type Tone =
    | 'new' | 'contacted' | 'qualified' | 'nurturing' | 'appointment'
    | 'proposal' | 'won' | 'lost' | 'archived'
    | 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const props = withDefaults(
    defineProps<{ tone?: Tone; label: string; dot?: boolean }>(),
    { tone: 'neutral', dot: false },
);

const TONES: Record<Tone, string> = {
    new: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    contacted: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300',
    qualified: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    nurturing: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    appointment: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    proposal: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    won: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    lost: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    archived: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    success: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    warning: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    danger: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    info: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    neutral: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};

const classes = computed(() => TONES[props.tone]);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-[var(--radius-pill)] px-2.5 py-0.5',
                'text-caption font-medium',
                classes,
            )
        "
    >
        <span v-if="dot" class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ label }}
    </span>
</template>
