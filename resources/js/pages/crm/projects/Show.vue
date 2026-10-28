<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

const props = defineProps<{
    project: {
        id: number;
        name: string;
        client_id: number;
        client: string | null;
        status: string;
        status_label: string;
        status_tone: string;
        description: string | null;
        starts_on: string | null;
        ends_on: string | null;
    };
    changeOrders: {
        id: number;
        number: string;
        title: string;
        amount: string;
        status: string;
        status_label: string;
        status_tone: string;
        reason: string | null;
    }[];
    statuses: { value: string; label: string }[];
}>();

const { can } = useAuthorization();

const statusOptions = computed(() =>
    props.statuses.map((status) => ({
        value: status.value,
        label: status.label,
    })),
);

const details = useForm({
    name: props.project.name,
    status: props.project.status,
    description: props.project.description ?? '',
    starts_on: props.project.starts_on ?? '',
    ends_on: props.project.ends_on ?? '',
});

const order = useForm({
    project_id: String(props.project.id),
    title: '',
    amount: '',
    reason: '',
});

function save(): void {
    details.patch(`/projects/${props.project.id}`);
}

function addOrder(): void {
    order.post('/change-orders', { preserveScroll: true, onSuccess: () => order.reset('title', 'amount', 'reason') });
}

function setStatus(id: number, status: string): void {
    router.patch(`/change-orders/${id}`, { status }, { preserveScroll: true });
}

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :title="project.name" :breadcrumbs="[{ label: 'Projects', href: '/projects' }, { label: project.name }]">
        <div class="space-y-4">
            <p class="text-muted">
                Client
                <Link :href="`/clients/${project.client_id}`" class="text-primary-600 hover:underline">{{ project.client }}</Link>
            </p>

            <form
                v-if="can('company.update')"
                class="rounded-xl border border-border bg-surface p-4"
                novalidate
                @submit.prevent="save"
            >
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <FormField
                        id="project-name"
                        label="Name"
                        required
                        :error="details.errors.name"
                    >
                        <TextInput
                            id="project-name"
                            v-model="details.name"
                            required
                        />
                    </FormField>
                    <FormField
                        id="project-status"
                        label="Status"
                        :error="details.errors.status"
                    >
                        <SelectMenu
                            id="project-status"
                            v-model="details.status"
                            :options="statusOptions"
                        />
                    </FormField>
                    <FormField
                        id="project-starts"
                        label="Starts"
                        :error="details.errors.starts_on"
                    >
                        <TextInput
                            id="project-starts"
                            v-model="details.starts_on"
                            type="date"
                        />
                    </FormField>
                    <FormField
                        id="project-ends"
                        label="Ends"
                        :error="details.errors.ends_on"
                    >
                        <TextInput
                            id="project-ends"
                            v-model="details.ends_on"
                            type="date"
                        />
                    </FormField>
                    <FormField
                        id="project-description"
                        class="sm:col-span-2"
                        label="Description"
                        :error="details.errors.description"
                    >
                        <TextArea
                            id="project-description"
                            v-model="details.description"
                            :rows="3"
                        />
                    </FormField>
                </div>
                <div class="mt-4">
                    <Button type="submit" :loading="details.processing">
                        Save project
                    </Button>
                </div>
            </form>

            <section class="space-y-3 rounded-xl border border-border bg-surface p-4">
                <h3 class="font-semibold text-strong">Change orders</h3>
                <p v-if="changeOrders.length === 0" class="text-[0.88rem] text-muted">Nothing has changed the original price yet.</p>
                <ul v-else class="space-y-2">
                    <li v-for="item in changeOrders" :key="item.id" class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-strong">{{ item.number }} · {{ item.title }}</p>
                            <p class="text-muted tabular-nums">{{ money(item.amount) }}</p>
                        </div>
                        <StatusBadge :tone="item.status_tone as never" :label="item.status_label" />
                        <div v-if="can('quote.update') && item.status === 'draft'" class="flex gap-2">
                            <Button type="button" size="sm" @click="setStatus(item.id, 'approved')">Approve</Button>
                            <Button type="button" size="sm" variant="ghost" @click="setStatus(item.id, 'rejected')">Reject</Button>
                        </div>
                    </li>
                </ul>

                <form v-if="can('quote.create')" class="grid gap-3 border-t border-border pt-3 sm:grid-cols-2" @submit.prevent="addOrder">
                    <FormField id="co-title" label="Title" required :error="order.errors.title">
                        <TextInput id="co-title" v-model="order.title" required />
                    </FormField>
                    <FormField id="co-amount" label="Amount" required :error="order.errors.amount">
                        <TextInput id="co-amount" v-model="order.amount" type="number" required />
                    </FormField>
                    <div class="sm:col-span-2">
                        <Button type="submit" :loading="order.processing">
                            Add change order
                        </Button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
