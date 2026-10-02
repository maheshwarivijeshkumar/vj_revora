<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        rows?: number;
        placeholder?: string;
        required?: boolean;
        invalid?: boolean;
        hint?: boolean;
        maxlength?: number;
    }>(),
    { rows: 5, required: false, invalid: false, hint: false },
);

defineEmits<{ 'update:modelValue': [value: string]; blur: [] }>();

const describedBy = computed(() => {
    const ids = [props.hint ? `${props.id}-hint` : null, props.invalid ? `${props.id}-error` : null];
    return ids.filter(Boolean).join(' ') || undefined;
});
</script>

<template>
    <textarea
        :id="id"
        :value="modelValue"
        :rows="rows"
        :placeholder="placeholder"
        :maxlength="maxlength"
        :aria-required="required || undefined"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        :class="
            cn(
                'w-full rounded-lg border bg-surface p-3.5 text-[0.95rem] leading-relaxed text-strong',
                'transition-colors placeholder:text-soft',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                invalid
                    ? 'border-danger focus-visible:outline-danger'
                    : 'border-border hover:border-border-strong',
            )
        "
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        @blur="$emit('blur')"
    />
</template>
