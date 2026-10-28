<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Line = { description: string; quantity: string; unit_price: string; amount: string };

const props = defineProps<{
    invoice: {
        id: number;
        number: string;
        title: string;
        status: string;
        status_label: string;
        status_tone: string;
        client_id: number;
        client: string | null;
        estimate_id: number | null;
        estimate_number: string | null;
        lines: Line[];
        tax_rate: string;
        subtotal: string;
        tax: string;
        total: string;
        paid_amount: string;
        issued_on: string | null;
        due_on: string | null;
        paid_on: string | null;
        notes: string | null;
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
    <Head :title="invoice.number" />

    <AppLayout :title="invoice.number" :breadcrumbs="[{ label: 'Invoices', href: '/invoices' }, { label: invoice.number }]">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-h3 font-semibold text-strong">{{ invoice.title }}</h2>
                    <p class="text-muted">
                        <Link :href="`/clients/${invoice.client_id}`" class="text-primary-600 hover:underline">{{ invoice.client }}</Link>
                        <template v-if="invoice.estimate_id">
                            · from
                            <Link :href="`/estimates/${invoice.estimate_id}`" class="text-primary-600 hover:underline">{{ invoice.estimate_number }}</Link>
                        </template>
                    </p>
                    <p v-if="invoice.due_on" class="text-[0.85rem] text-muted">Due {{ invoice.due_on }}</p>
                </div>
                <StatusBadge :tone="invoice.status_tone as never" :label="invoice.status_label" />
            </div>

            <div v-if="can('billing.manage')" class="flex flex-wrap gap-2">
                <Button v-if="invoice.status === 'draft'" type="button" variant="secondary" @click="post(`/invoices/${invoice.id}/send`)">Mark sent</Button>
                <Button v-if="invoice.status === 'sent' || invoice.status === 'overdue'" type="button" @click="post(`/invoices/${invoice.id}/pay`)">Mark paid</Button>
                <Button v-if="invoice.status !== 'paid' && invoice.status !== 'void'" type="button" variant="ghost" @click="post(`/invoices/${invoice.id}/void`)">Void</Button>
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
                        <tr v-for="(line, index) in invoice.lines" :key="index" class="border-t border-border">
                            <td class="px-4 py-3">{{ line.description }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ line.quantity }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ money(line.unit_price) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ money(line.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <dl class="max-w-xs space-y-1 text-[0.88rem]">
                <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular-nums">{{ money(invoice.subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Tax {{ invoice.tax_rate }}%</dt><dd class="tabular-nums">{{ money(invoice.tax) }}</dd></div>
                <div class="flex justify-between font-semibold text-strong"><dt>Total</dt><dd class="tabular-nums">{{ money(invoice.total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Paid</dt><dd class="tabular-nums">{{ money(invoice.paid_amount) }}</dd></div>
            </dl>
        </div>
    </AppLayout>
</template>
