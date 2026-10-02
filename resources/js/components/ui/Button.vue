<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const button = cva(
    [
        'inline-flex items-center justify-center gap-2 font-medium whitespace-nowrap',
        'rounded-[var(--radius-control)] transition-colors duration-150',
        'focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:pointer-events-none disabled:opacity-50',
    ],
    {
        variants: {
            variant: {
                primary:
                    'bg-primary-600 text-white hover:bg-primary-700 focus-visible:outline-primary-600',
                brand: 'brand-gradient text-white hover:opacity-90 focus-visible:outline-brand',
                secondary:
                    'bg-surface-alt text-strong border border-border hover:bg-surface-sunken',
                ghost: 'text-body hover:bg-surface-alt hover:text-strong',
                danger: 'bg-danger text-white hover:opacity-90 focus-visible:outline-danger',
                link: 'text-primary-600 underline-offset-4 hover:underline',
            },
            size: {
                sm: 'h-8 px-3 text-small',
                md: 'h-9 px-4 text-body',
                lg: 'h-11 px-6 text-body-lg',
                icon: 'size-9',
            },
        },
        defaultVariants: { variant: 'primary', size: 'md' },
    },
);

type ButtonVariants = VariantProps<typeof button>;

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariants['variant'];
        size?: ButtonVariants['size'];
        loading?: boolean;
        disabled?: boolean;
        type?: 'button' | 'submit' | 'reset';
    }>(),
    { type: 'button', loading: false, disabled: false },
);

// Blocking clicks while loading is what actually prevents the duplicate
// submits required by §116 — a spinner alone does not.
const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        :aria-busy="loading || undefined"
        :class="cn(button({ variant, size }))"
    >
        <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <slot />
    </button>
</template>
