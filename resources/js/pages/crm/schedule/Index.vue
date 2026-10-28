<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

const { timeZone } = useClock();

type Entry = {
    id: number;
    title: string;
    starts_at: string;
    location: string | null;
    client: string | null;
    project: string | null;
    crew: string | null;
};

const props = defineProps<{
    entries: Entry[];
    clients: { id: number; name: string }[];
    projects: { id: number; name: string; client_id: number }[];
    crew: { id: number; name: string }[];
}>();

const { can } = useAuthorization();
const creating = ref(false);

const columns: Column<Entry>[] = [
    { key: 'title', label: 'What' },
    { key: 'starts_at', label: 'Starts' },
    { key: 'crew', label: 'Crew' },
    { key: 'client', label: 'Client' },
    { key: 'location', label: 'Location', secondary: true },
];

const clientOptions = computed(() =>
    props.clients.map((client) => ({ value: String(client.id), label: client.name })),
);

const crewOptions = computed(() =>
    props.crew.map((member) => ({ value: String(member.id), label: member.name })),
);

const form = useForm({
    title: '',
    starts_at: '',
    location: '',
    client_id: '',
    project_id: '',
    crew_member_id: '',
    notes: '',
});

const projectOptions = computed(() =>
    props.projects
        .filter((project) => form.client_id === '' || String(project.client_id) === form.client_id)
        .map((project) => ({ value: String(project.id), label: project.name })),
);

function create(): void {
    form.post('/schedule', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}

function when(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        timeZone: timeZone.value,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Schedule" />

    <AppLayout title="Schedule" :breadcrumbs="[{ label: 'Schedule' }]">
        <template #actions>
            <Button v-if="can('task.create')" type="button" @click="creating = true">
                New visit
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Visits and blocks of work. Book a client, a project, and someone from the crew.
            </p>

            <RecordList
                :rows="entries"
                :columns="columns"
                resource="schedule"
                :can-delete="can('task.delete')"
                noun="visit"
                plural="visits"
                placeholder="Search the schedule"
                empty-title="Nothing scheduled"
                empty-description="Add a visit. Yesterday onward stays on this list."
                :row-key-label="(row) => row.title"
            >
                <template #cell:title="{ row }">
                    <span class="font-medium text-strong">{{ row.title }}</span>
                </template>
                <template #cell:starts_at="{ row }">
                    {{ when(row.starts_at) }}
                </template>
                <template #cell:crew="{ row }">
                    {{ row.crew ?? '—' }}
                </template>
                <template #cell:client="{ row }">
                    {{ row.client ?? '—' }}
                </template>
                <template #cell:location="{ row }">
                    {{ row.location ?? '—' }}
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            title="New visit"
            description="The list stays where it is while you book this."
            size="lg"
            :busy="form.processing"
            @close="creating = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="create">
                <FormField id="visit-title" label="What" required :error="form.errors.title">
                    <TextInput id="visit-title" v-model="form.title" required placeholder="Site visit" />
                </FormField>
                <FormField id="visit-when" label="Starts" required :error="form.errors.starts_at">
                    <TextInput id="visit-when" v-model="form.starts_at" type="text" required placeholder="2026-10-29 09:00" />
                </FormField>
                <FormField id="visit-where" label="Location" :error="form.errors.location">
                    <TextInput id="visit-where" v-model="form.location" />
                </FormField>
                <FormField id="visit-client" label="Client" :error="form.errors.client_id">
                    <SelectMenu id="visit-client" v-model="form.client_id" :options="clientOptions" placeholder="None" clearable />
                </FormField>
                <FormField id="visit-project" label="Project" :error="form.errors.project_id">
                    <SelectMenu id="visit-project" v-model="form.project_id" :options="projectOptions" placeholder="None" clearable />
                </FormField>
                <FormField id="visit-crew" label="Crew" :error="form.errors.crew_member_id">
                    <SelectMenu id="visit-crew" v-model="form.crew_member_id" :options="crewOptions" placeholder="Unassigned" clearable />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">Add to schedule</Button>
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>
