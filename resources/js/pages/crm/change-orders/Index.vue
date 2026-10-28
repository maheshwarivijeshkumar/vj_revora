<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Order = {
    id: number;
    number: string;
    title: string;
    amount: string;
    status_label: string;
    status_tone: string;
    project_id: number;
    project: string | null;
    client: string | null;
};

const props = defineProps<{
    orders: Order[];
    projects: { id: number; name: string; client: string | null }[];
}>();

const projectOptions = computed(() =>
    props.projects.map((project) => ({
        value: String(project.id),
        label: project.client
            ? `${project.name} · ${project.client}`
            : project.name,
    })),
);

const { can } = useAuthorization();

const form = useForm({
    project_id: '',
    title: '',
    amount: '',
    reason: '',
});

function create(): void {
    form.post('/change-orders', { preserveScroll: true, onSuccess: () => form.reset('title', 'amount', 'reason') });
}

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Change orders" />

    <AppLayout title="Change orders" :breadcrumbs="[{ label: 'Change orders' }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Extra work or a price change on a project, after the original estimate.
            </p>

            <form v-if="can('quote.create')" class="grid items-start gap-4 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2" @submit.prevent="create">
                <FormField id="co-project" label="Project" required :error="form.errors.project_id">
                    <SelectMenu
                        id="co-project"
                        v-model="form.project_id"
                        :options="projectOptions"
                        placeholder="Choose a project"
                        :invalid="Boolean(form.errors.project_id)"
                    />
                </FormField>
                <FormField id="co-title" label="Title" required :error="form.errors.title">
                    <TextInput id="co-title" v-model="form.title" required />
                </FormField>
                <FormField id="co-amount" label="Amount" required :error="form.errors.amount">
                    <TextInput id="co-amount" v-model="form.amount" type="number" required />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">Add change order</Button>
                </div>
            </form>

            <EmptyState v-if="orders.length === 0" title="No change orders" description="Open a project, then record a change here." />

            <div v-else class="overflow-hidden rounded-xl border border-border bg-surface">
                <table class="w-full text-left text-[0.9rem]">
                    <thead class="border-b border-border bg-surface-alt text-[0.75rem] font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="order in orders" :key="order.id" class="hover:bg-surface-alt">
                            <td class="px-4 py-3">
                                <p class="font-medium text-strong">{{ order.number }}</p>
                                <p class="text-muted">{{ order.title }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="`/projects/${order.project_id}`" class="hover:underline">{{ order.project }}</Link>
                                <p class="text-muted">{{ order.client }}</p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ money(order.amount) }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :tone="order.status_tone as never" :label="order.status_label" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
