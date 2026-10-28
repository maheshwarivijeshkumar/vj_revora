<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, Link } from '@inertiajs/vue3';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

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

const { can } = useAuthorization();

const columns: Column<FileRow>[] = [
    { key: 'title', label: 'File' },
    { key: 'subject', label: 'Attached to' },
    { key: 'size_bytes', label: 'Size', numeric: true },
    { key: 'uploader', label: 'Added by', secondary: true },
    { key: 'created_at', label: 'Added' },
];

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

    return new Date(value).toLocaleDateString(undefined, {
        timeZone: timeZone.value,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Documents" />

    <AppLayout title="Documents" :breadcrumbs="[{ label: 'Documents' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Files already attached to leads and deals. Open one to download it.
            </p>

            <RecordList
                :rows="documents"
                :columns="columns"
                resource="documents"
                :can-delete="can('document.delete')"
                noun="document"
                plural="documents"
                placeholder="Search documents"
                empty-title="No documents yet"
                empty-description="Attach a file on a lead or a deal and it will show up here."
                :row-key-label="(row) => row.title"
            >
                <template #cell:title="{ row }">
                    <Link :href="row.href" class="font-medium text-strong hover:underline">{{ row.title }}</Link>
                </template>
                <template #cell:subject="{ row }">
                    {{ row.subject ?? '—' }}
                </template>
                <template #cell:size_bytes="{ row }">
                    {{ size(row.size_bytes) }}
                </template>
                <template #cell:uploader="{ row }">
                    {{ row.uploader ?? '—' }}
                </template>
                <template #cell:created_at="{ row }">
                    {{ when(row.created_at) }}
                </template>
            </RecordList>
        </div>
    </AppLayout>
</template>
