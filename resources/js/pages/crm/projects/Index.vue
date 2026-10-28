<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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
    form.post('/projects');
}
</script>

<template>
    <Head title="Projects" />

    <AppLayout title="Projects" :breadcrumbs="[{ label: 'Projects' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                A project opens when a lead is won. Add another when the same client has a second job.
            </p>

            <form
                v-if="can('company.create')"
                class="rounded-xl border border-border bg-surface p-4"
                novalidate
                @submit.prevent="create"
            >
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <FormField
                        id="project-client"
                        label="Client"
                        required
                        :error="form.errors.client_id"
                    >
                        <SelectMenu
                            id="project-client"
                            v-model="form.client_id"
                            :options="clientOptions"
                            placeholder="Choose a client"
                            :invalid="Boolean(form.errors.client_id)"
                        />
                    </FormField>
                    <FormField
                        id="project-name"
                        label="Project name"
                        required
                        :error="form.errors.name"
                    >
                        <TextInput
                            id="project-name"
                            v-model="form.name"
                            :invalid="Boolean(form.errors.name)"
                            required
                        />
                    </FormField>
                    <FormField
                        id="project-starts"
                        label="Starts"
                        :error="form.errors.starts_on"
                    >
                        <TextInput
                            id="project-starts"
                            v-model="form.starts_on"
                            type="date"
                        />
                    </FormField>
                    <FormField
                        id="project-ends"
                        label="Ends"
                        :error="form.errors.ends_on"
                    >
                        <TextInput
                            id="project-ends"
                            v-model="form.ends_on"
                            type="date"
                        />
                    </FormField>
                    <FormField
                        id="project-description"
                        class="sm:col-span-2"
                        label="Description"
                        :error="form.errors.description"
                    >
                        <TextArea
                            id="project-description"
                            v-model="form.description"
                            :rows="3"
                        />
                    </FormField>
                </div>
                <div class="mt-4">
                    <Button type="submit" :loading="form.processing">
                        Open project
                    </Button>
                </div>
            </form>

            <EmptyState v-if="projects.length === 0" title="No projects yet" description="Win a lead to open the first one." />

            <div
                v-else
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <table class="w-full text-left text-[0.9rem]">
                    <thead
                        class="border-b border-border bg-surface-alt text-[0.75rem] font-semibold tracking-wide text-muted uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Change orders</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="project in projects"
                            :key="project.id"
                            class="hover:bg-surface-alt"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/projects/${project.id}`"
                                    class="font-medium text-strong hover:underline"
                                    >{{ project.name }}</Link
                                >
                            </td>
                            <td class="px-4 py-3 text-body">
                                {{ project.client ?? '—' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ project.change_orders_count }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :tone="project.status_tone as never"
                                    :label="project.status_label"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
