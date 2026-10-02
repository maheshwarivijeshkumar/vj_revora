<script setup lang="ts">
import { X } from 'lucide-vue-next';

/**
 * Contextual bar shown while rows are selected (§113).
 *
 * The count is passed down to the slot because destructive actions must state
 * the exact number of affected records before they run.
 */
defineProps<{ count: number }>();

const emit = defineEmits<{ clear: [] }>();
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="-translate-y-1 opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="count > 0"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-primary-500 bg-primary-50 px-3 py-2 dark:bg-primary-900/30"
            role="region"
            aria-label="Bulk actions"
        >
            <span class="text-[0.9rem] font-medium text-strong tabular-nums">
                {{ count }} selected
            </span>

            <span class="h-4 w-px bg-border-strong" aria-hidden="true" />

            <slot :count="count" />

            <button
                type="button"
                class="ml-auto flex items-center gap-1 rounded-md px-2 py-1 text-[0.85rem] text-muted transition-colors hover:text-strong"
                @click="emit('clear')"
            >
                <X class="size-3.5" />
                Clear
            </button>
        </div>
    </Transition>
</template>
