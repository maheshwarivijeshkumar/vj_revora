<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(defineProps<{ class?: string }>(), { class: '' });
</script>

<template>
    <div :class="cn('animate-pulse rounded-[var(--radius-control)] bg-surface-sunken', $props.class)" />
</template>
