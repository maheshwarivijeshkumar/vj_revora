<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    items: { question: string; answer: string }[];
}>();

// Single-open accordion: FAQ answers are short, and letting several stand open
// pushes the rest of the list off-screen.
const openIndex = ref<number | null>(null);

function toggle(i: number): void {
    openIndex.value = openIndex.value === i ? null : i;
}
</script>

<template>
    <ul
        class="divide-y divide-border overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface"
    >
        <li v-for="(item, i) in items" :key="item.question">
            <h3>
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-surface-alt"
                    :aria-expanded="openIndex === i"
                    :aria-controls="`faq-panel-${i}`"
                    @click="toggle(i)"
                >
                    <span class="font-medium text-body text-strong">
                        {{ item.question }}
                    </span>
                    <ChevronDown
                        class="size-4.5 shrink-0 text-muted transition-transform duration-200"
                        :class="openIndex === i && 'rotate-180'"
                        aria-hidden="true"
                    />
                </button>
            </h3>

            <div
                v-show="openIndex === i"
                :id="`faq-panel-${i}`"
                class="px-5 pb-4 text-pretty text-body text-muted"
            >
                {{ item.answer }}
            </div>
        </li>
    </ul>
</template>
