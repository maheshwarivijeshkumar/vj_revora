<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarDays,
    Handshake,
    LayoutGrid,
    Sparkles,
    TrendingUp,
    UsersRound,
} from 'lucide-vue-next';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Dashboard shell.
 *
 * The metrics below are placeholders wired to zero — the real dashboard is
 * widget-driven off the dashboard_widgets catalog and per-user layouts
 * (§36, §80), which lands in Phase 1.10. This page exists so the shell,
 * theming and navigation are demonstrably working end to end.
 */
const metrics = [
    { label: 'Total Leads', value: '0', icon: UsersRound },
    { label: 'Qualified Leads', value: '0', icon: TrendingUp },
    { label: 'Open Deals', value: '0', icon: Handshake },
    { label: 'Appointments', value: '0', icon: CalendarDays },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard" :breadcrumbs="[{ label: 'Dashboard' }]">
        <template #actions>
            <Button variant="secondary" size="sm">
                <LayoutGrid class="size-4" />
                Add Widget
            </Button>
        </template>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard
                v-for="metric in metrics"
                :key="metric.label"
                :label="metric.label"
                :value="metric.value"
                :icon="metric.icon"
            />
        </div>

        <Card class="mt-4" :padded="false">
            <EmptyState
                title="Your dashboard is empty"
                description="Connect a lead source or create your first lead to start seeing data here."
            >
                <template #icon>
                    <Sparkles class="size-5" />
                </template>
                <template #actions>
                    <Button variant="primary" size="sm">Connect Source</Button>
                    <Button variant="secondary" size="sm">Create Lead</Button>
                </template>
            </EmptyState>
        </Card>
    </AppLayout>
</template>
