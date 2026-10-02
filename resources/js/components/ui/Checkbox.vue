<script setup lang="ts">
import { Check, Minus } from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Checkbox with a visible custom box.
 *
 * The native input is kept in the DOM and only visually hidden, so keyboard
 * focus, form submission, screen readers and browser autofill all keep
 * working. Styling the real control with `appearance-none` instead would lose
 * the indeterminate state, which the DataTable header needs (§110.1).
 */
const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: boolean;
        indeterminate?: boolean;
        disabled?: boolean;
        invalid?: boolean;
        required?: boolean;
    }>(),
    { indeterminate: false, disabled: false, invalid: false, required: false },
);

defineEmits<{ 'update:modelValue': [value: boolean] }>();

const checked = computed(() => props.modelValue || props.indeterminate);
</script>

<template>
    <span class="relative inline-flex shrink-0 items-center">
        <input
            :id="id"
            type="checkbox"
            class="peer absolute size-5 cursor-pointer opacity-0 disabled:cursor-not-allowed"
            :checked="modelValue"
            :disabled="disabled"
            :aria-required="required || undefined"
            :aria-invalid="invalid || undefined"
            :aria-describedby="invalid ? `${id}-error` : undefined"
            :indeterminate="indeterminate"
            @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
        />
        <span
            aria-hidden="true"
            :class="
                cn(
                    'flex size-5 items-center justify-center rounded-[5px] border-2 transition-colors',
                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-600',
                    'peer-disabled:opacity-50',
                    checked
                        ? 'border-primary-600 bg-primary-600 text-white'
                        : invalid
                          ? 'border-danger bg-surface'
                          : 'border-border-strong bg-surface peer-hover:border-primary-500',
                )
            "
        >
            <Minus v-if="indeterminate" class="size-3.5" stroke-width="3" />
            <Check v-else-if="modelValue" class="size-3.5" stroke-width="3.5" />
        </span>
    </span>
</template>
