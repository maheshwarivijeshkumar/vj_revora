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

const { can } = useAuthorization();
const creating = ref(false);

const columns: Column<Order>[] = [
    { key: 'number', label: 'Order' },
    { key: 'project', label: 'Project' },
    { key: 'amount', label: 'Amount', numeric: true },
    { key: 'status_label', label: 'Status' },
];

const projectOptions = computed(() =>
    props.projects.map((project) => ({
        value: String(project.id),
        label: project.client ? `${project.name} · ${project.client}` : project.name,
    })),
);

const form = useForm({
    project_id: '',
    title: '',
    amount: '',
    reason: '',
});

function create(): void {
    form.post('/change-orders', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'amount', 'reason');
            creating.value = false;
        },
    });
}

function money(value: string): string {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Change orders" />

    <AppLayout title="Change orders" :breadcrumbs="[{ label: 'Change orders' }]">
        <template #actions>
            <Button v-if="can('quote.create')" type="button" @click="creating = true">
                New change order
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Extra work or a price change on a project, after the original estimate.
            </p>

            <RecordList
                :rows="orders"
                :columns="columns"
                resource="change-orders"
                :can-delete="can('quote.delete')"
                noun="change order"
                plural="change orders"
                placeholder="Search change orders"
                empty-title="No change orders"
                empty-description="Open a project, then record a change here."
                :row-key-label="(row) => row.number"
            >
                <template #cell:number="{ row }">
                    <p class="font-medium text-strong">{{ row.number }}</p>
                    <p class="text-muted">{{ row.title }}</p>
                </template>
                <template #cell:project="{ row }">
                    <Link :href="`/projects/${row.project_id}`" class="hover:underline">{{ row.project }}</Link>
                    <p class="text-muted">{{ row.client }}</p>
                </template>
                <template #cell:amount="{ row }">
                    {{ money(row.amount) }}
                </template>
                <template #cell:status_label="{ row }">
                    <StatusBadge :tone="row.status_tone as never" :label="row.status_label" />
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            title="New change order"
            description="The list stays where it is while you record the change."
            size="lg"
            :busy="form.processing"
            @close="creating = false"
        >
            <form class="grid items-start gap-4 sm:grid-cols-2" @submit.prevent="create">
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
        </Drawer>
    </AppLayout>
</template>
