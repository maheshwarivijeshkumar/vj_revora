<script setup lang="ts">
import { CircleAlert } from 'lucide-vue-next';

/**
 * Everything wrong with the form, at the top of it.
 *
 * WCAG 3.3.1 asks for errors to be identified in text. A summary also means
 * somebody who submitted a long form does not have to hunt through twenty
 * fields to find which two failed — and each entry moves focus to its field,
 * so fixing them is a matter of clicking down the list.
 *
 * `role="alert"` rather than a plain region: it appears after a submit, which
 * is exactly when a screen-reader user needs to be told without having to go
 * looking.
 */
defineProps<{
    errors: { field: string; id: string; label: string; message: string }[];
}>();

function focus(id: string): void {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    element.focus({ preventScroll: true });
    element.scrollIntoView({ block: 'center', behavior: 'smooth' });
}
</script>

<template>
    <div
        v-if="errors.length"
        role="alert"
        aria-live="assertive"
        class="rounded-xl border border-danger bg-danger-soft p-4"
    >
        <p class="flex items-center gap-2 text-[0.92rem] font-semibold text-danger">
            <CircleAlert class="size-4 shrink-0" aria-hidden="true" />
            {{
                errors.length === 1
                    ? 'One field needs attention'
                    : `${errors.length} fields need attention`
            }}
        </p>

        <ul class="mt-2 space-y-1">
            <li v-for="error in errors" :key="error.field">
                <button
                    type="button"
                    class="text-left text-[0.88rem] text-danger underline decoration-danger/40 underline-offset-2 transition-colors hover:decoration-danger"
                    @click="focus(error.id)"
                >
                    <span class="font-medium">{{ error.label }}:</span>
                    {{ error.message }}
                </button>
            </li>
        </ul>
    </div>
</template>
