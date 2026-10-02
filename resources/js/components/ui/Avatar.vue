<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg' }>(),
    { size: 'md', src: null },
);

const initials = computed(() =>
    props.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

const sizes = { sm: 'size-7 text-caption', md: 'size-9 text-small', lg: 'size-12 text-body' };
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full',
                'bg-primary-100 font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-200',
                sizes[size],
            )
        "
    >
        <img v-if="src" :src="src" :alt="name" class="size-full object-cover" />
        <span v-else aria-hidden="true">{{ initials }}</span>
        <span v-if="!src" class="sr-only">{{ name }}</span>
    </span>
</template>
