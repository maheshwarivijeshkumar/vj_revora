<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import PricedForm from '@/components/crm/PricedForm.vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
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
const creating = ref(false);

const columns: Column<InvoiceRow>[] = [
    { key: 'number', label: 'Invoice' },
    { key: 'client', label: 'Client' },
    { key: 'total', label: 'Total', numeric: true },
    { key: 'due_on', label: 'Due' },
    { key: 'status_label', label: 'Status' },
];

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Invoices" />

    <AppLayout title="Invoices" :breadcrumbs="[{ label: 'Invoices' }]">
        <template #actions>
            <Button v-if="can('billing.manage')" type="button" @click="creating = true">
                New invoice
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Invoice a client directly, or create one from an accepted estimate. Mark it sent, then paid when the money arrives.
            </p>

            <RecordList
                :rows="invoices"
                :columns="columns"
                resource="invoices"
                :can-delete="can('billing.manage')"
                noun="invoice"
                plural="invoices"
                placeholder="Search invoices"
                empty-title="No invoices yet"
                empty-description="Accept an estimate, or write an invoice for a client."
                :row-key-label="(row) => row.number"
            >
                <template #cell:number="{ row }">
                    <Link :href="`/invoices/${row.id}`" class="font-medium text-strong hover:underline">
                        {{ row.number }}
                    </Link>
                    <p class="text-muted">{{ row.title }}</p>
                </template>
                <template #cell:total="{ row }">
                    {{ money(row.total) }}
                </template>
                <template #cell:due_on="{ row }">
                    {{ row.due_on ?? '—' }}
                </template>
                <template #cell:status_label="{ row }">
                    <StatusBadge :tone="row.status_tone as never" :label="row.status_label" />
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            title="New invoice"
            description="The list stays where it is while you write the invoice."
            size="lg"
            @close="creating = false"
        >
            <PricedForm plain action="/invoices" :clients="clients" submit-label="Save invoice" @saved="creating = false" />
        </Drawer>
    </AppLayout>
</template>
