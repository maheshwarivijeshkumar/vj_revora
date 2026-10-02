<script setup lang="ts">
/**
 * Label, required marker, hint and error message for one field.
 *
 * Required fields carry a red asterisk *and* the word "required" in the
 * screen-reader label: an asterisk alone is meaningless to anyone not looking
 * at it, and colour alone is not a reliable signal (§119, §127).
 */
withDefaults(
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
</script>

<template>
    <div>
        <label :for="id" class="block text-[0.88rem] font-medium text-strong">
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true">*</span>
            <span v-if="required" class="sr-only">(required)</span>
            <span v-else-if="optionalLabel" class="font-normal text-soft">
                (optional)
            </span>
        </label>

        <p v-if="hint" :id="`${id}-hint`" class="mt-1 text-[0.82rem] text-muted">
            {{ hint }}
        </p>

        <div class="mt-1.5">
            <slot />
        </div>

        <!-- role="alert" so the message is announced when it appears, rather
             than only being found by someone who navigates back to the field. -->
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="mt-1.5 text-[0.85rem] text-danger"
        >
            {{ error }}
        </p>
    </div>
</template>
