<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        type?: 'text' | 'email' | 'tel' | 'url' | 'password' | 'number' | 'date';
        placeholder?: string;
        autocomplete?: string;
        required?: boolean;
        invalid?: boolean;
        hint?: boolean;
        disabled?: boolean;
    }>(),
    {
        type: 'text',
        required: false,
        invalid: false,
        hint: false,
        disabled: false,
    },
);

defineEmits<{ 'update:modelValue': [value: string]; blur: [] }>();

// Points the field at whichever of its descriptions currently exist, so a
// screen reader reads the error rather than the hint once one appears.
const describedBy = computed(() => {
    const ids = [props.hint ? `${props.id}-hint` : null, props.invalid ? `${props.id}-error` : null];
    return ids.filter(Boolean).join(' ') || undefined;
});
</script>

<template>
    <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :aria-required="required || undefined"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        :disabled="disabled"
        :class="
            cn(
                'h-11 w-full rounded-lg border bg-surface px-3.5 text-[0.95rem] text-strong',
                'transition-colors placeholder:text-soft',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                invalid
                    ? 'border-danger focus-visible:outline-danger'
                    : 'border-border hover:border-border-strong',
                // Visibly inert rather than merely unresponsive: a field that
                // looks editable and ignores typing reads as a bug.
                disabled &&
                    'cursor-not-allowed bg-surface-alt text-muted hover:border-border',
            )
        "
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur')"
    />
</template>
