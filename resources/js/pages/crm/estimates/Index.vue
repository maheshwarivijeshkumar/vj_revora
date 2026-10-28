<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PricedForm from '@/components/crm/PricedForm.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type EstimateRow = {
    id: number;
    number: string;
    title: string;
    client: string | null;
    status_label: string;
    status_tone: string;
    total: string;
};

defineProps<{
    estimates: EstimateRow[];
    clients: { id: number; name: string }[];
}>();

const { can } = useAuthorization();

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Estimates" />

    <AppLayout title="Estimates" :breadcrumbs="[{ label: 'Estimates' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Price the work for a client. Accept it, then create the invoice from that estimate.
            </p>

            <PricedForm v-if="can('quote.create')" action="/estimates" :clients="clients" submit-label="Save estimate" />

            <EmptyState v-if="estimates.length === 0" title="No estimates yet" description="Add a client first, then price the work here." />

            <div v-else class="overflow-hidden rounded-xl border border-border bg-surface">
                <table class="w-full text-left text-[0.9rem]">
                    <thead class="border-b border-border bg-surface-alt text-[0.75rem] font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th class="px-4 py-3">Estimate</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="estimate in estimates" :key="estimate.id" class="hover:bg-surface-alt">
                            <td class="px-4 py-3">
                                <Link :href="`/estimates/${estimate.id}`" class="font-medium text-strong hover:underline">
                                    {{ estimate.number }}
                                </Link>
                                <p class="text-muted">{{ estimate.title }}</p>
                            </td>
                            <td class="px-4 py-3">{{ estimate.client }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ money(estimate.total) }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :tone="estimate.status_tone as never" :label="estimate.status_label" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
