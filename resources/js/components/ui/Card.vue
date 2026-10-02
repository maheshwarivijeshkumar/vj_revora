<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(defineProps<{ padded?: boolean }>(), { padded: true });
</script>

<template>
    <div
        :class="
            cn(
                'rounded-[var(--radius-card)] border border-border bg-surface shadow-card',
                padded && 'p-5',
            )
        "
    >
        <slot />
    </div>
</template>
