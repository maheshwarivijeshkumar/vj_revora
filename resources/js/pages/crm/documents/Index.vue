<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const { timeZone } = useClock();
type FileRow = {
    id: number;
    title: string;
    mime_type: string;
    size_bytes: number;
    subject: string | null;
    uploader: string | null;
    created_at: string | null;
    href: string;
};

defineProps<{ documents: FileRow[] }>();

function size(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${Math.round(bytes / 1024)} KB`;
}

function when(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, { timeZone: timeZone.value, day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Documents" />

    <AppLayout title="Documents" :breadcrumbs="[{ label: 'Documents' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Files already attached to leads and deals. Open one to download it.
            </p>

            <EmptyState v-if="documents.length === 0" title="No documents yet" description="Attach a file on a lead or a deal and it will show up here." />

            <ul v-else class="divide-y divide-border overflow-hidden rounded-xl border border-border">
                <li v-for="file in documents" :key="file.id" class="bg-surface px-4 py-3">
                    <Link :href="file.href" class="font-medium text-strong hover:underline">{{ file.title }}</Link>
                    <p class="text-[0.85rem] text-muted">
                        {{ [file.subject, file.uploader, size(file.size_bytes), when(file.created_at)].filter(Boolean).join(' · ') }}
                    </p>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
