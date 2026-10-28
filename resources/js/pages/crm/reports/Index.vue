<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Card = { label: string; value: string; href: string };
type ReportRow = Card & { id: number };

const props = defineProps<{
    cards: Card[];
}>();

const rows = computed<ReportRow[]>(() =>
    props.cards.map((card, index) => ({ id: index + 1, ...card })),
);

const columns: Column<ReportRow>[] = [
    { key: 'label', label: 'Report' },
    { key: 'value', label: 'Value', numeric: true },
];
</script>

<template>
    <Head title="Reports" />

    <AppLayout title="Reports" :breadcrumbs="[{ label: 'Reports' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Clients, open work, and the money still owed. Each number opens the list behind it.
            </p>

            <RecordList
                :rows="rows"
                :columns="columns"
                noun="report"
                plural="reports"
                placeholder="Search reports"
                empty-title="No reports yet"
                empty-description="Numbers appear here once clients, projects, and invoices exist."
                :row-key-label="(row) => row.label"
            >
                <template #cell:label="{ row }">
                    <Link :href="row.href" class="font-medium text-strong hover:underline">
                        {{ row.label }}
                    </Link>
                </template>
                <template #actions="{ row }">
                    <Link :href="row.href" class="text-[0.85rem] font-medium text-primary-600 hover:underline">
                        Open
                    </Link>
                </template>
            </RecordList>
        </div>
    </AppLayout>
</template>
