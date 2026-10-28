<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type ProjectRow = {
    id: number;
    name: string;
    client: string | null;
    status_label: string;
    status_tone: string;
    change_orders_count: number;
};

const props = defineProps<{
    projects: ProjectRow[];
    clients: { id: number; name: string }[];
}>();

const { can } = useAuthorization();
const creating = ref(false);

const columns: Column<ProjectRow>[] = [
    { key: 'name', label: 'Project' },
    { key: 'client', label: 'Client' },
    { key: 'change_orders_count', label: 'Change orders', numeric: true },
    { key: 'status_label', label: 'Status' },
];

const clientOptions = computed(() =>
    props.clients.map((client) => ({
        value: String(client.id),
        label: client.name,
    })),
);

const form = useForm({
    client_id: '',
    name: '',
    description: '',
    starts_on: '',
    ends_on: '',
});

function create(): void {
    form.post('/projects', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}
</script>

<template>
    <Head title="Projects" />

    <AppLayout title="Projects" :breadcrumbs="[{ label: 'Projects' }]">
        <template #actions>
            <Button v-if="can('company.create')" type="button" @click="creating = true">
                New project
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                A project opens when a lead is won. Add another when the same client has a second job.
            </p>

            <RecordList
                :rows="projects"
                :columns="columns"
                resource="projects"
                :can-delete="can('company.delete')"
                noun="project"
                plural="projects"
                placeholder="Search projects"
                empty-title="No projects yet"
                empty-description="Win a lead to open the first one."
                :row-key-label="(row) => row.name"
            >
                <template #cell:name="{ row }">
                    <Link :href="`/projects/${row.id}`" class="font-medium text-strong hover:underline">
                        {{ row.name }}
                    </Link>
                </template>
                <template #cell:client="{ row }">
                    {{ row.client ?? '—' }}
                </template>
                <template #cell:status_label="{ row }">
                    <StatusBadge :tone="row.status_tone as never" :label="row.status_label" />
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            title="New project"
            description="The list stays where it is while you fill this in."
            size="lg"
            :busy="form.processing"
            @close="creating = false"
        >
            <form class="grid items-start gap-4 sm:grid-cols-2" novalidate @submit.prevent="create">
                <FormField id="project-client" label="Client" required :error="form.errors.client_id">
                    <SelectMenu
                        id="project-client"
                        v-model="form.client_id"
                        :options="clientOptions"
                        placeholder="Choose a client"
                        :invalid="Boolean(form.errors.client_id)"
                    />
                </FormField>
                <FormField id="project-name" label="Project name" required :error="form.errors.name">
                    <TextInput id="project-name" v-model="form.name" :invalid="Boolean(form.errors.name)" required />
                </FormField>
                <FormField id="project-starts" label="Starts" :error="form.errors.starts_on">
                    <TextInput id="project-starts" v-model="form.starts_on" type="date" />
                </FormField>
                <FormField id="project-ends" label="Ends" :error="form.errors.ends_on">
                    <TextInput id="project-ends" v-model="form.ends_on" type="date" />
                </FormField>
                <FormField id="project-description" class="sm:col-span-2" label="Description" :error="form.errors.description">
                    <TextArea id="project-description" v-model="form.description" :rows="3" />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">Open project</Button>
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>
