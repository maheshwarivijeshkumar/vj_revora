<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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

const clientOptions = computed(() =>
    props.clients.map((client) => ({
        value: String(client.id),
        label: client.name,
    })),
);

const crewOptions = computed(() =>
    props.crew.map((member) => ({
        value: String(member.id),
        label: member.name,
    })),
);

const { can } = useAuthorization();

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
        .filter(
            (project) =>
                form.client_id === '' ||
                String(project.client_id) === form.client_id,
        )
        .map((project) => ({
            value: String(project.id),
            label: project.name,
        })),
);

function create(): void {
    form.post('/schedule', { preserveScroll: true, onSuccess: () => form.reset() });
}

function remove(id: number): void {
    router.delete(`/schedule/${id}`, { preserveScroll: true });
}

function when(value: string): string {
    return new Date(value).toLocaleString(undefined, { timeZone: timeZone.value,
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
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Visits and blocks of work. Book a client, a project, and someone from the crew.
            </p>

            <form v-if="can('task.create')" class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2" @submit.prevent="create">
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
                    <SelectMenu
                        id="visit-client"
                        v-model="form.client_id"
                        :options="clientOptions"
                        placeholder="None"
                        clearable
                    />
                </FormField>
                <FormField id="visit-project" label="Project" :error="form.errors.project_id">
                    <SelectMenu
                        id="visit-project"
                        v-model="form.project_id"
                        :options="projectOptions"
                        placeholder="None"
                        clearable
                    />
                </FormField>
                <FormField id="visit-crew" label="Crew" :error="form.errors.crew_member_id">
                    <SelectMenu
                        id="visit-crew"
                        v-model="form.crew_member_id"
                        :options="crewOptions"
                        placeholder="Unassigned"
                        clearable
                    />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">Add to schedule</Button>
                </div>
            </form>

            <EmptyState v-if="entries.length === 0" title="Nothing scheduled" description="Add a visit above. Yesterday onward stays on this list." />

            <ul v-else class="divide-y divide-border overflow-hidden rounded-xl border border-border">
                <li v-for="entry in entries" :key="entry.id" class="flex flex-wrap items-center justify-between gap-3 bg-surface px-4 py-3">
                    <div>
                        <p class="font-medium text-strong">{{ entry.title }}</p>
                        <p class="text-[0.85rem] text-muted">
                            {{ when(entry.starts_at) }}
                            <template v-if="entry.crew"> · {{ entry.crew }}</template>
                            <template v-if="entry.location"> · {{ entry.location }}</template>
                            <template v-if="entry.client"> · {{ entry.client }}</template>
                        </p>
                    </div>
                    <Button
                        v-if="can('task.delete')"
                        type="button"
                        variant="danger"
                        size="icon"
                        :aria-label="`Remove ${entry.title}`"
                        @click="remove(entry.id)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
