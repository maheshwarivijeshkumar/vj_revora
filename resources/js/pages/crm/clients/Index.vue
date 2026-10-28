<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormField from '@/components/ui/FormField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type ClientRow = {
    id: number;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    status_label: string;
    status_tone: string;
    projects_count: number;
    invoices_count: number;
    lead_id: number | null;
};

const props = defineProps<{
    clients: ClientRow[];
    filters: { search: string };
}>();

const { can } = useAuthorization();

const query = ref(props.filters.search ?? '');

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company_name: '',
    notes: '',
});

function search(): void {
    router.get(
        '/clients',
        { search: query.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function create(): void {
    form.post('/clients');
}
</script>

<template>
    <Head title="Clients" />

    <AppLayout title="Clients" :breadcrumbs="[{ label: 'Clients' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                A client is a lead that was won, or someone you add here. Estimates, projects, and invoices all hang off the client.
            </p>

            <form
                class="flex max-w-xl items-center gap-2"
                @submit.prevent="search"
            >
                <div class="min-w-0 flex-1">
                    <TextInput
                        id="client-search"
                        v-model="query"
                        placeholder="Search name, company, or email"
                    />
                </div>
                <Button type="submit" variant="secondary" class="shrink-0">
                    Search
                </Button>
            </form>

            <form
                v-if="can('company.create')"
                class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2"
                @submit.prevent="create"
            >
                <FormField id="client-name" label="Name" required :error="form.errors.name">
                    <TextInput id="client-name" v-model="form.name" :invalid="!!form.errors.name" required />
                </FormField>
                <FormField id="client-company" label="Company" :error="form.errors.company_name">
                    <TextInput id="client-company" v-model="form.company_name" />
                </FormField>
                <FormField id="client-email" label="Email" :error="form.errors.email">
                    <TextInput id="client-email" v-model="form.email" type="email" />
                </FormField>
                <FormField id="client-phone" label="Phone" :error="form.errors.phone">
                    <TextInput id="client-phone" v-model="form.phone" type="tel" />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">
                        Add client
                    </Button>
                </div>
            </form>

            <EmptyState v-if="clients.length === 0" title="No clients yet" description="Win a lead, or add a client above." />

            <div
                v-else
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <table class="w-full text-left text-[0.9rem]">
                    <thead
                        class="border-b border-border bg-surface-alt text-[0.75rem] font-semibold tracking-wide text-muted uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Projects</th>
                            <th class="px-4 py-3">Invoices</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="client in clients"
                            :key="client.id"
                            class="hover:bg-surface-alt"
                        >
                            <td class="px-4 py-3">
                                <Link :href="`/clients/${client.id}`" class="font-medium text-strong hover:underline">
                                    {{ client.name }}
                                </Link>
                                <p v-if="client.company_name" class="text-muted">{{ client.company_name }}</p>
                            </td>
                            <td class="px-4 py-3 text-body">{{ client.email || client.phone || '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ client.projects_count }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ client.invoices_count }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :tone="client.status_tone as never" :label="client.status_label" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
