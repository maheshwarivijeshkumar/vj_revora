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
const creating = ref(false);

const columns: Column<EstimateRow>[] = [
    { key: 'number', label: 'Estimate' },
    { key: 'client', label: 'Client' },
    { key: 'total', label: 'Total', numeric: true },
    { key: 'status_label', label: 'Status' },
];

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Estimates" />

    <AppLayout title="Estimates" :breadcrumbs="[{ label: 'Estimates' }]">
        <template #actions>
            <Button v-if="can('quote.create')" type="button" @click="creating = true">
                New estimate
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Price the work for a client. Accept it, then create the invoice from that estimate.
            </p>

            <RecordList
                :rows="estimates"
                :columns="columns"
                resource="estimates"
                :can-delete="can('quote.delete')"
                noun="estimate"
                plural="estimates"
                placeholder="Search estimates"
                empty-title="No estimates yet"
                empty-description="Add a client first, then price the work here."
                :row-key-label="(row) => row.number"
            >
                <template #cell:number="{ row }">
                    <Link :href="`/estimates/${row.id}`" class="font-medium text-strong hover:underline">
                        {{ row.number }}
                    </Link>
                    <p class="text-muted">{{ row.title }}</p>
                </template>
                <template #cell:total="{ row }">
                    {{ money(row.total) }}
                </template>
                <template #cell:status_label="{ row }">
                    <StatusBadge :tone="row.status_tone as never" :label="row.status_label" />
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            title="New estimate"
            description="The list stays where it is while you price the work."
            size="lg"
            @close="creating = false"
        >
            <PricedForm plain action="/estimates" :clients="clients" submit-label="Save estimate" @saved="creating = false" />
        </Drawer>
    </AppLayout>
</template>
