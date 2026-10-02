<script setup lang="ts">
/**
 * Empty state (§114).
 *
 * Always explains *why* the view is empty and offers the action that would
 * fill it. A bare "No results" leaves the user with nowhere to go.
 */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
        <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-surface-alt text-muted">
            <slot name="icon" />
        </div>
        <h3 class="text-h4 text-strong">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-body text-muted">
            {{ description }}
        </p>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
