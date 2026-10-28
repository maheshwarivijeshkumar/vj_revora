<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PricedForm from '@/components/crm/PricedForm.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type InvoiceRow = {
    id: number;
    number: string;
    title: string;
    client: string | null;
    status_label: string;
    status_tone: string;
    total: string;
    due_on: string | null;
};

defineProps<{
    invoices: InvoiceRow[];
    clients: { id: number; name: string }[];
}>();

const { can } = useAuthorization();

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Invoices" />

    <AppLayout title="Invoices" :breadcrumbs="[{ label: 'Invoices' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Invoice a client directly, or create one from an accepted estimate. Mark it sent, then paid when the money arrives.
            </p>

            <PricedForm v-if="can('billing.manage')" action="/invoices" :clients="clients" submit-label="Save invoice" />

            <EmptyState v-if="invoices.length === 0" title="No invoices yet" description="Accept an estimate, or write an invoice for a client." />

            <div v-else class="overflow-hidden rounded-xl border border-border bg-surface">
                <table class="w-full text-left text-[0.9rem]">
                    <thead class="border-b border-border bg-surface-alt text-[0.75rem] font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-surface-alt">
                            <td class="px-4 py-3">
                                <Link :href="`/invoices/${invoice.id}`" class="font-medium text-strong hover:underline">{{ invoice.number }}</Link>
                                <p class="text-muted">{{ invoice.title }}</p>
                            </td>
                            <td class="px-4 py-3">{{ invoice.client }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ money(invoice.total) }}</td>
                            <td class="px-4 py-3">{{ invoice.due_on ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :tone="invoice.status_tone as never" :label="invoice.status_label" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
