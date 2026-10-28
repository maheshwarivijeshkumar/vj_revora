<script setup lang="ts">
import { computed, provide } from 'vue';
import type { FieldDescription } from '@/composables/useFieldDescription';

/**
 * Label, control, then the hint and error underneath the control.
 *
 * Required fields carry a red asterisk and the word "required" in the
 * screen-reader label: an asterisk alone is meaningless to anyone not looking
 * at it, and colour alone is not a reliable signal (§119, §127).
 *
 * The hint sits under the control so a row of fields lines up on the inputs,
 * and the explanation stays next to the value it describes.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        required?: boolean;
        hint?: string;
        error?: string;
        optionalLabel?: boolean;
    }>(),
    { required: false, optionalLabel: false },
);

provide(
    'formField',
    computed(
        (): FieldDescription => ({
            hintId: props.hint ? `${props.id}-hint` : undefined,
            errorId: props.error ? `${props.id}-error` : undefined,
        }),
    ),
);
</script>

<template>
    <div class="min-w-0">
        <label :for="id" class="block text-[0.8125rem] font-medium text-strong">
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true">*</span>
            <span v-if="required" class="sr-only">(required)</span>
            <span v-else-if="optionalLabel" class="font-normal text-soft">
                (optional)
            </span>
        </label>

        <div class="mt-1.5">
            <slot />
        </div>

        <p
            v-if="hint"
            :id="`${id}-hint`"
            class="mt-1.5 text-[0.82rem] leading-snug text-muted"
        >
            {{ hint }}
        </p>

        <!-- role="alert" so the message is announced when it appears, rather
             than only being found by someone who navigates back to the field. -->
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="mt-1.5 text-[0.85rem] leading-snug text-danger"
        >
            {{ error }}
        </p>
    </div>
</template>
