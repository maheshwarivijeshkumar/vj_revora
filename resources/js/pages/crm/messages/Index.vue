<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/ui/EmptyState.vue';
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

function when(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString(undefined, { timeZone: timeZone.value, day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <Head title="Messages" />

    <AppLayout title="Messages" :breadcrumbs="[{ label: 'Messages' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Email and WhatsApp already sent or received on leads. Open the lead to reply.
            </p>

            <EmptyState v-if="messages.length === 0" title="No messages yet" description="A message appears here after you write to a lead, or they write back." />

            <ul v-else class="divide-y divide-border overflow-hidden rounded-xl border border-border">
                <li v-for="message in messages" :key="message.id" class="bg-surface px-4 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-medium text-strong">{{ message.subject || message.channel }}</p>
                        <p class="text-[0.8rem] text-muted">{{ when(message.at) }}</p>
                    </div>
                    <p class="text-[0.85rem] text-muted">
                        {{ message.direction }} · {{ message.channel }}
                        <template v-if="message.lead_id">
                            ·
                            <Link :href="`/leads/${message.lead_id}`" class="text-primary-600 hover:underline">{{ message.lead }}</Link>
                        </template>
                    </p>
                    <p v-if="message.body" class="mt-1 text-[0.88rem] text-body">{{ message.body }}</p>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
