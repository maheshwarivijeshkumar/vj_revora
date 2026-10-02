<script setup lang="ts">
import { TrendingDown, TrendingUp } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';
import Card from '@/components/ui/Card.vue';
import Skeleton from '@/components/ui/Skeleton.vue';

/**
 * Dashboard KPI card (§35).
 *
 * Carries icon, value, trend, comparison period and a click-through target.
 * Loading is a skeleton rather than a blank card (§114).
 */
const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        icon: Component;
        trend?: number | null;
        comparison?: string;
        loading?: boolean;
        /** Set when a higher number is worse, e.g. response time or churn. */
        invertTrend?: boolean;
    }>(),
    { trend: null, loading: false, invertTrend: false },
);

const isPositive = computed(() => {
    if (props.trend === null) return null;
    return props.invertTrend ? props.trend < 0 : props.trend > 0;
});
</script>

<template>
    <Card>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-small font-medium text-muted">
                    {{ label }}
                </p>

                <Skeleton v-if="loading" class="mt-2 h-8 w-24" />
                <p
                    v-else
                    class="mt-1 text-h1 font-bold text-strong tabular-nums"
                >
                    {{ value }}
                </p>
            </div>

            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-primary-50 text-primary-600 dark:bg-primary-900/40 dark:text-primary-300"
            >
                <component :is="icon" class="size-4.5" aria-hidden="true" />
            </div>
        </div>

        <div
            v-if="trend !== null && !loading"
            class="mt-3 flex items-center gap-1.5 text-small"
        >
            <component
                :is="isPositive ? TrendingUp : TrendingDown"
                :class="[
                    'size-4',
                    isPositive ? 'text-emerald-600' : 'text-red-600',
                ]"
                aria-hidden="true"
            />
            <span
                :class="isPositive ? 'text-emerald-600' : 'text-red-600'"
                class="font-medium"
            >
                {{ trend > 0 ? '+' : '' }}{{ trend }}%
            </span>
            <span v-if="comparison" class="text-muted">{{ comparison }}</span>
        </div>
    </Card>
</template>
