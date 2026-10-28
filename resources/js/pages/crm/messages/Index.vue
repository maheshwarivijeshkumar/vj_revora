<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, Link } from '@inertiajs/vue3';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const { timeZone } = useClock();

type MessageRow = {
    id: number;
    channel: string;
    direction: string;
    subject: string | null;
    body: string | null;
    lead_id: number | null;
    lead: string | null;
    at: string | null;
};

defineProps<{ messages: MessageRow[] }>();

const columns: Column<MessageRow>[] = [
    { key: 'subject', label: 'Message' },
    { key: 'channel', label: 'Channel' },
    { key: 'direction', label: 'Direction' },
    { key: 'lead', label: 'Lead' },
    { key: 'at', label: 'When' },
];

function when(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        timeZone: timeZone.value,
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Messages" />

    <AppLayout title="Messages" :breadcrumbs="[{ label: 'Messages' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Email and WhatsApp already sent or received on leads. Open the lead to reply. Messages stay on the record, so this list has no delete.
            </p>

            <RecordList
                :rows="messages"
                :columns="columns"
                noun="message"
                plural="messages"
                placeholder="Search messages"
                empty-title="No messages yet"
                empty-description="A message appears here after you write to a lead, or they write back."
                :row-key-label="(row) => row.subject || row.channel"
            >
                <template #cell:subject="{ row }">
                    <p class="font-medium text-strong">{{ row.subject || row.channel }}</p>
                    <p v-if="row.body" class="text-muted">{{ row.body }}</p>
                </template>
                <template #cell:lead="{ row }">
                    <Link
                        v-if="row.lead_id"
                        :href="`/leads/${row.lead_id}`"
                        class="text-primary-600 hover:underline"
                    >
                        {{ row.lead }}
                    </Link>
                    <span v-else>—</span>
                </template>
                <template #cell:at="{ row }">
                    {{ when(row.at) }}
                </template>
            </RecordList>
        </div>
    </AppLayout>
</template>
