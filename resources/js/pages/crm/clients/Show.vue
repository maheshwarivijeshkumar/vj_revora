<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Row = { id: number; name?: string; number?: string; title?: string; status_label: string; status_tone: string; total?: string; invoice_id?: number | null };

const props = defineProps<{
    client: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        company_name: string | null;
        notes: string | null;
        status: string;
        status_label: string;
        status_tone: string;
        lead_id: number | null;
        lead_name: string | null;
    };
    projects: { id: number; name: string; status_label: string; status_tone: string }[];
    estimates: Row[];
    invoices: Row[];
}>();

const { can } = useAuthorization();

const form = useForm({
    name: props.client.name,
    email: props.client.email ?? '',
    phone: props.client.phone ?? '',
    company_name: props.client.company_name ?? '',
    notes: props.client.notes ?? '',
    status: props.client.status,
});

function save(): void {
    form.patch(`/clients/${props.client.id}`);
}

function money(value: string | undefined): string {
    if (!value) {
        return '';
    }

    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="client.name" />

    <AppLayout title="Client" :breadcrumbs="[{ label: 'Clients', href: '/clients' }, { label: client.name }]">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-h3 font-semibold text-strong">{{ client.name }}</h2>
                    <p v-if="client.company_name" class="text-muted">{{ client.company_name }}</p>
                </div>
                <StatusBadge :tone="client.status_tone as never" :label="client.status_label" />
            </div>

            <p v-if="client.lead_id" class="text-[0.88rem] text-muted">
                Converted from
                <Link :href="`/leads/${client.lead_id}`" class="text-primary-600 hover:underline">{{ client.lead_name }}</Link>.
            </p>

            <form v-if="can('company.update')" class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2" @submit.prevent="save">
                <FormField id="edit-name" label="Name" required :error="form.errors.name">
                    <TextInput id="edit-name" v-model="form.name" required />
                </FormField>
                <FormField id="edit-company" label="Company" :error="form.errors.company_name">
                    <TextInput id="edit-company" v-model="form.company_name" />
                </FormField>
                <FormField id="edit-email" label="Email" :error="form.errors.email">
                    <TextInput id="edit-email" v-model="form.email" type="email" />
                </FormField>
                <FormField id="edit-phone" label="Phone" :error="form.errors.phone">
                    <TextInput id="edit-phone" v-model="form.phone" type="tel" />
                </FormField>
                <FormField id="edit-status" label="Status" :error="form.errors.status">
                    <SelectMenu
                        id="edit-status"
                        v-model="form.status"
                        :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ]"
                    />
                </FormField>
                <FormField id="edit-notes" label="Notes" class="sm:col-span-2" :error="form.errors.notes">
                    <TextArea id="edit-notes" v-model="form.notes" :rows="3" />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">
                        Save client
                    </Button>
                </div>
            </form>

            <section class="rounded-xl border border-border bg-surface p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-strong">Projects</h3>
                    <Link href="/projects" class="text-[0.85rem] text-primary-600 hover:underline">All projects</Link>
                </div>
                <p v-if="projects.length === 0" class="text-[0.88rem] text-muted">No project yet.</p>
                <ul v-else class="space-y-2">
                    <li v-for="project in projects" :key="project.id" class="flex items-center justify-between gap-3">
                        <Link :href="`/projects/${project.id}`" class="hover:underline">{{ project.name }}</Link>
                        <StatusBadge :tone="project.status_tone as never" :label="project.status_label" />
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-border bg-surface p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-strong">Estimates</h3>
                    <Link href="/estimates" class="text-[0.85rem] text-primary-600 hover:underline">New estimate</Link>
                </div>
                <p v-if="estimates.length === 0" class="text-[0.88rem] text-muted">No estimate yet. Price the work, then turn an accepted estimate into an invoice.</p>
                <ul v-else class="space-y-2">
                    <li v-for="estimate in estimates" :key="estimate.id" class="flex flex-wrap items-center justify-between gap-3">
                        <Link :href="`/estimates/${estimate.id}`" class="hover:underline">{{ estimate.number }} · {{ estimate.title }}</Link>
                        <span class="text-muted tabular-nums">{{ money(estimate.total) }}</span>
                        <StatusBadge :tone="estimate.status_tone as never" :label="estimate.status_label" />
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-border bg-surface p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-strong">Invoices</h3>
                    <Link href="/invoices" class="text-[0.85rem] text-primary-600 hover:underline">New invoice</Link>
                </div>
                <p v-if="invoices.length === 0" class="text-[0.88rem] text-muted">No invoice yet.</p>
                <ul v-else class="space-y-2">
                    <li v-for="invoice in invoices" :key="invoice.id" class="flex flex-wrap items-center justify-between gap-3">
                        <Link :href="`/invoices/${invoice.id}`" class="hover:underline">{{ invoice.number }} · {{ invoice.title }}</Link>
                        <span class="text-muted tabular-nums">{{ money(invoice.total) }}</span>
                        <StatusBadge :tone="invoice.status_tone as never" :label="invoice.status_label" />
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
