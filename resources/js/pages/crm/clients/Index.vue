<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
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

defineProps<{
    clients: ClientRow[];
    filters: { search: string };
}>();

const { can } = useAuthorization();
const creating = ref(false);

const columns: Column<ClientRow>[] = [
    { key: 'name', label: 'Client' },
    { key: 'email', label: 'Contact' },
    { key: 'projects_count', label: 'Projects', numeric: true },
    { key: 'invoices_count', label: 'Invoices', numeric: true },
    { key: 'status_label', label: 'Status' },
];

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company_name: '',
    notes: '',
});

function search(value: string): void {
    router.get('/clients', { search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function create(): void {
    form.post('/clients', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}
</script>

<template>
    <Head title="Clients" />

    <AppLayout title="Clients" :breadcrumbs="[{ label: 'Clients' }]">
        <template #actions>
            <Button v-if="can('company.create')" type="button" @click="creating = true">
                New client
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                A client is a lead that was won, or someone you add here. Estimates, projects, and invoices all hang off the client.
            </p>

            <RecordList
                :rows="clients"
                :columns="columns"
                resource="clients"
                :can-delete="can('company.delete')"
                noun="client"
                plural="clients"
                placeholder="Search name, company, or email"
                remote-search
                :search="filters.search"
                empty-title="No clients yet"
                empty-description="Win a lead, or add a client."
                :row-key-label="(row) => row.name"
                @search="search"
            >
                <template #cell:name="{ row }">
                    <Link :href="`/clients/${row.id}`" class="font-medium text-strong hover:underline">
                        {{ row.name }}
                    </Link>
                    <p v-if="row.company_name" class="text-muted">{{ row.company_name }}</p>
                </template>
                <template #cell:email="{ row }">
                    {{ row.email || row.phone || '—' }}
                </template>
                <template #cell:status_label="{ row }">
                    <StatusBadge :tone="row.status_tone as never" :label="row.status_label" />
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            title="New client"
            description="The list stays where it is while you fill this in."
            size="lg"
            :busy="form.processing"
            @close="creating = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="create">
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
                    <Button type="submit" :loading="form.processing">Add client</Button>
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>
