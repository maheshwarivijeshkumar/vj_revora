<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    cards: { label: string; value: string; href: string }[];
}>();
</script>

<template>
    <Head title="Reports" />

    <AppLayout title="Reports" :breadcrumbs="[{ label: 'Reports' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Clients, open work, and the money still owed. Each number opens the list behind it.
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="card in cards"
                    :key="card.label"
                    :href="card.href"
                    class="rounded-xl border border-border bg-surface p-4 hover:border-primary-600"
                >
                    <p class="text-[0.82rem] text-muted">{{ card.label }}</p>
                    <p class="mt-1 text-h3 font-semibold tabular-nums text-strong">{{ card.value }}</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
