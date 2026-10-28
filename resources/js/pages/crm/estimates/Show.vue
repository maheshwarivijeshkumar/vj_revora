<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Line = { description: string; quantity: string; unit_price: string; amount: string };

const props = defineProps<{
    estimate: {
        id: number;
        number: string;
        title: string;
        status: string;
        status_label: string;
        status_tone: string;
        client_id: number;
        client: string | null;
        lines: Line[];
        tax_rate: string;
        subtotal: string;
        tax: string;
        total: string;
        notes: string | null;
        invoice_id: number | null;
    };
}>();

const { can } = useAuthorization();

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function post(path: string): void {
    router.post(path, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="estimate.number" />

    <AppLayout :title="estimate.number" :breadcrumbs="[{ label: 'Estimates', href: '/estimates' }, { label: estimate.number }]">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-h3 font-semibold text-strong">{{ estimate.title }}</h2>
                    <p class="text-muted">
                        <Link :href="`/clients/${estimate.client_id}`" class="text-primary-600 hover:underline">{{ estimate.client }}</Link>
                    </p>
                </div>
                <StatusBadge :tone="estimate.status_tone as never" :label="estimate.status_label" />
            </div>

            <div v-if="can('quote.update') || can('billing.manage')" class="flex flex-wrap gap-2">
                <Button v-if="estimate.status === 'draft' && can('quote.update')" type="button" variant="secondary" @click="post(`/estimates/${estimate.id}/send`)">Mark sent</Button>
                <Button v-if="(estimate.status === 'draft' || estimate.status === 'sent') && can('quote.update')" type="button" @click="post(`/estimates/${estimate.id}/accept`)">Accept</Button>
                <Button v-if="estimate.status !== 'declined' && !estimate.invoice_id && can('quote.update')" type="button" variant="ghost" @click="post(`/estimates/${estimate.id}/decline`)">Decline</Button>
                <Button v-if="estimate.status === 'accepted' && can('billing.manage')" type="button" @click="post(`/estimates/${estimate.id}/invoice`)">
                    {{ estimate.invoice_id ? 'Open invoice' : 'Create invoice' }}
                </Button>
                <Link v-if="estimate.invoice_id" :href="`/invoices/${estimate.invoice_id}`" class="inline-flex h-9 items-center text-primary-600 hover:underline">View invoice</Link>
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-left text-[0.88rem]">
                    <thead class="bg-surface-alt text-muted">
                        <tr>
                            <th class="px-4 py-2 font-medium">Description</th>
                            <th class="px-4 py-2 font-medium">Qty</th>
                            <th class="px-4 py-2 font-medium">Price</th>
                            <th class="px-4 py-2 font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in estimate.lines" :key="index" class="border-t border-border">
                            <td class="px-4 py-3">{{ line.description }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ line.quantity }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ money(line.unit_price) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ money(line.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <dl class="max-w-xs space-y-1 text-[0.88rem]">
                <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular-nums">{{ money(estimate.subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Tax {{ estimate.tax_rate }}%</dt><dd class="tabular-nums">{{ money(estimate.tax) }}</dd></div>
                <div class="flex justify-between font-semibold text-strong"><dt>Total</dt><dd class="tabular-nums">{{ money(estimate.total) }}</dd></div>
            </dl>

            <p v-if="estimate.notes" class="text-[0.88rem] text-body">{{ estimate.notes }}</p>
        </div>
    </AppLayout>
</template>
