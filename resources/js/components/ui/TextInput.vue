<script setup lang="ts">
import { Check, ChevronDown, ChevronUp, CircleAlert } from 'lucide-vue-next';
import { useAttrs } from 'vue';
import { useFieldDescription } from '@/composables/useFieldDescription';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        type?: 'text' | 'email' | 'tel' | 'url' | 'password' | 'number' | 'date';
        placeholder?: string;
        autocomplete?: string;
        min?: string | number;
        max?: string | number;
        step?: string | number;
        required?: boolean;
        invalid?: boolean;
        hint?: boolean;
        disabled?: boolean;
        /** Touched, and nothing wrong with it. Shows a quiet tick. */
        settled?: boolean;
    }>(),
    {
        type: 'text',
        required: false,
        invalid: false,
        hint: false,
        disabled: false,
        settled: false,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string]; blur: [] }>();

function stepBy(direction: 1 | -1): void {
    if (props.disabled) {
        return;
    }

    const amount = Number(props.step ?? 1);
    const delta = Number.isFinite(amount) && amount > 0 ? amount : 1;
    const current = Number(props.modelValue);
    let next =
        Math.round(((Number.isFinite(current) ? current : 0) + direction * delta) * 1e8) /
        1e8;

    if (props.min !== undefined && props.min !== '') {
        const min = Number(props.min);

        if (Number.isFinite(min)) {
            next = Math.max(min, next);
        }
    }

    if (props.max !== undefined && props.max !== '') {
        const max = Number(props.max);

        if (Number.isFinite(max)) {
            next = Math.min(max, next);
        }
    }

    const stepText = String(props.step ?? '1');
    const places = stepText.includes('.') ? (stepText.split('.')[1]?.length ?? 0) : 0;

    emit('update:modelValue', places > 0 ? next.toFixed(places) : String(next));
}

// Points the field at the hint and error under it, including when FormField
// owns those messages and this input was not told about them separately.
const describedBy = useFieldDescription(props);
const attrs = useAttrs();
</script>

<template>
    <!-- Wrapped so the state icon can sit inside the field without the input
         having to become a container. -->
    <div class="relative">
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :min="min"
            :max="max"
            :step="step"
            :aria-required="required || undefined"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            :disabled="disabled"
            :class="
                cn(
                    'h-11 w-full rounded-lg border bg-surface px-3.5 py-0 text-[0.95rem] text-strong',
                    'transition-colors placeholder:text-soft',
                    type === 'number' && 'pr-10',
                    'focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-primary-600',
                    invalid
                        ? 'border-danger focus-visible:outline-danger'
                        : 'border-border hover:border-border-strong',
                    // Visibly inert rather than merely unresponsive: a field
                    // that looks editable and ignores typing reads as a bug.
                    disabled &&
                        'cursor-not-allowed bg-surface-alt text-muted hover:border-border',
                    (invalid || settled) && (type === 'number' ? 'pr-16' : 'pr-10'),
                    attrs.class as string,
                )
            "
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
            @blur="$emit('blur')"
        />

        <!-- Icons, not colour alone: colour is not a reliable signal, and a
             red border says nothing to anyone who cannot see it (§127). The
             message itself is in FormField, where a screen reader reads it. -->
        <div
            v-if="type === 'number'"
            class="absolute inset-y-px right-px flex w-7 flex-col overflow-hidden rounded-r-[7px] border-l border-border bg-surface-alt"
        >
            <button
                type="button"
                class="flex min-h-0 flex-1 items-center justify-center text-muted transition-colors hover:bg-surface-sunken hover:text-strong focus-visible:bg-surface-sunken focus-visible:text-strong focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40"
                :disabled="disabled"
                aria-label="Increase"
                @mousedown.prevent
                @click="stepBy(1)"
            >
                <ChevronUp class="size-3.5" />
            </button>
            <button
                type="button"
                class="flex min-h-0 flex-1 items-center justify-center border-t border-border text-muted transition-colors hover:bg-surface-sunken hover:text-strong focus-visible:bg-surface-sunken focus-visible:text-strong focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40"
                :disabled="disabled"
                aria-label="Decrease"
                @mousedown.prevent
                @click="stepBy(-1)"
            >
                <ChevronDown class="size-3.5" />
            </button>
        </div>

        <span
            v-if="invalid || settled"
            class="pointer-events-none absolute inset-y-0 flex items-center"
            :class="type === 'number' ? 'right-9' : 'right-3'"
            aria-hidden="true"
        >
            <CircleAlert v-if="invalid" class="size-4 text-danger" />
            <Check v-else class="size-4 text-success" />
        </span>
    </div>
</template>
