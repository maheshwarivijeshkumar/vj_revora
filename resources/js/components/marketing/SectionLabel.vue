<script setup lang="ts">
/**
 * Section marker: a rule, a two-digit index and a label.
 *
 * Replaces the coloured eyebrow-over-centred-heading pattern. It gives the
 * page a spine you can scan, and reads as editorial rather than templated.
 */
defineProps<{ index: string; label: string }>();
</script>

<template>
    <div class="flex items-center gap-3">
        <span class="font-mono text-[0.78rem] text-soft tabular-nums">{{
            index
        }}</span>
        <span class="h-px w-8 bg-border-strong" aria-hidden="true" />
        <span
            class="text-[0.72rem] font-semibold tracking-[0.13em] text-muted uppercase"
        >
            {{ label }}
        </span>
    </div>
</template>
