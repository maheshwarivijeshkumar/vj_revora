<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Field = { key: string; label: string; required?: boolean; type?: string };
type Row = { id: number; name: string; detail: string | null; phone: string | null; email: string | null; extra: string | null };

const props = defineProps<{
    kind: 'crew' | 'subcontractors' | 'materials';
    title: string;
    intro: string;
    rows: Row[];
    fields: Field[];
}>();

const { can } = useAuthorization();

const canCreate = props.kind === 'materials' ? can('product.create') : can('company.create');
const canDelete = props.kind === 'materials' ? can('product.delete') : can('company.delete');

const initial: Record<string, string> = {};
for (const field of props.fields) {
    initial[field.key] = field.key === 'unit' ? 'each' : '';
}

const form = useForm(initial);

function create(): void {
    form.post(`/${props.kind}`, { preserveScroll: true, onSuccess: () => form.reset() });
}

function remove(id: number): void {
    router.delete(`/${props.kind}/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title" :breadcrumbs="[{ label: title }]">
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.88rem] text-muted">{{ intro }}</p>

            <form v-if="canCreate" class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2" @submit.prevent="create">
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
                    <Button type="submit" :disabled="form.processing">Add</Button>
                </div>
            </form>

            <EmptyState v-if="rows.length === 0" :title="`No ${title.toLowerCase()} yet`" description="Add the first one above." />

            <ul v-else class="divide-y divide-border overflow-hidden rounded-xl border border-border">
                <li v-for="row in rows" :key="row.id" class="flex flex-wrap items-center justify-between gap-3 bg-surface px-4 py-3">
                    <div>
                        <p class="font-medium text-strong">{{ row.name }}</p>
                        <p class="text-[0.85rem] text-muted">
                            {{ [row.detail, row.extra, row.phone, row.email].filter(Boolean).join(' · ') || '—' }}
                        </p>
                    </div>
                    <Button
                        v-if="canDelete"
                        type="button"
                        variant="danger"
                        size="icon"
                        :aria-label="`Remove ${row.name}`"
                        @click="remove(row.id)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
