<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { Column } from '@/components/data/DataTable.vue';
import RecordList from '@/components/data/RecordList.vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Field = { key: string; label: string; required?: boolean; type?: string };
type Row = {
    id: number;
    name: string;
    detail: string | null;
    phone: string | null;
    email: string | null;
    extra: string | null;
};

const props = defineProps<{
    kind: 'crew' | 'subcontractors' | 'materials';
    title: string;
    intro: string;
    rows: Row[];
    fields: Field[];
}>();

const { can } = useAuthorization();
const creating = ref(false);

const columns: Column<Row>[] = [
    { key: 'name', label: 'Name' },
    { key: 'detail', label: 'Detail' },
    { key: 'extra', label: 'Extra', secondary: true },
    { key: 'phone', label: 'Phone' },
    { key: 'email', label: 'Email', secondary: true },
];

const canCreate = computed(() => (props.kind === 'materials' ? can('product.create') : can('company.create')));
const canDelete = computed(() => (props.kind === 'materials' ? can('product.delete') : can('company.delete')));

const noun = computed(() => {
    if (props.kind === 'crew') {
        return 'crew member';
    }

    if (props.kind === 'materials') {
        return 'material';
    }

    return 'subcontractor';
});

const plural = computed(() => {
    if (props.kind === 'crew') {
        return 'crew members';
    }

    if (props.kind === 'materials') {
        return 'materials';
    }

    return 'subcontractors';
});

function blank(): Record<string, string> {
    const values: Record<string, string> = {};

    for (const field of props.fields) {
        values[field.key] = field.key === 'unit' ? 'each' : '';
    }

    return values;
}

const form = useForm(blank());

watch(
    () => props.kind,
    () => {
        form.defaults(blank());
        form.reset();
        form.clearErrors();
        creating.value = false;
    },
);

function create(): void {
    form.post(`/${props.kind}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creating.value = false;
        },
    });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title" :breadcrumbs="[{ label: title }]">
        <template #actions>
            <Button v-if="canCreate" type="button" @click="creating = true">
                New {{ noun }}
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">{{ intro }}</p>

            <RecordList
                :rows="rows"
                :columns="columns"
                :resource="kind"
                :can-delete="canDelete"
                :noun="noun"
                :plural="plural"
                :placeholder="`Search ${plural}`"
                :empty-title="`No ${plural} yet`"
                empty-description="Add the first one with the button above."
                :row-key-label="(row) => row.name"
            >
                <template #cell:name="{ row }">
                    <span class="font-medium text-strong">{{ row.name }}</span>
                </template>
                <template #cell:detail="{ row }">
                    {{ row.detail ?? '—' }}
                </template>
                <template #cell:extra="{ row }">
                    {{ row.extra ?? '—' }}
                </template>
                <template #cell:phone="{ row }">
                    {{ row.phone ?? '—' }}
                </template>
                <template #cell:email="{ row }">
                    {{ row.email ?? '—' }}
                </template>
            </RecordList>
        </div>

        <Drawer
            :open="creating"
            :title="`New ${noun}`"
            description="The list stays where it is while you fill this in."
            size="lg"
            :busy="form.processing"
            @close="creating = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="create">
                <FormField
                    v-for="field in fields"
                    :id="`${kind}-${field.key}`"
                    :key="field.key"
                    :label="field.label"
                    :required="field.required"
                    :error="(form.errors as Record<string, string>)[field.key]"
                >
                    <TextInput
                        :id="`${kind}-${field.key}`"
                        v-model="form[field.key]"
                        :type="field.type === 'number' ? 'number' : 'text'"
                        :required="field.required"
                    />
                </FormField>
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="form.processing">Add</Button>
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>
