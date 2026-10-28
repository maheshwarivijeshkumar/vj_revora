<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';

type ClientOption = { id: number; name: string };

const props = defineProps<{
    action: string;
    clients: ClientOption[];
    submitLabel: string;
    /** Drops the card chrome when the form sits inside a drawer. */
    plain?: boolean;
}>();

const emit = defineEmits<{ saved: [] }>();

const form = useForm({
    client_id: '',
    title: '',
    tax_rate: '0',
    notes: '',
    lines: [{ description: '', quantity: '1', unit_price: '' }],
});

function addLine(): void {
    form.lines.push({ description: '', quantity: '1', unit_price: '' });
}

function removeLine(index: number): void {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
}

function lineError(index: number, field: string): string | undefined {
    const errors = form.errors as Record<string, string>;

    return errors[`lines.${index}.${field}`];
}

const clientOptions = computed(() =>
    props.clients.map((client) => ({
        value: String(client.id),
        label: client.name,
    })),
);

function submit(): void {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
    });
}
</script>

<template>
    <form
        class="space-y-3"
        :class="plain ? '' : 'rounded-xl border border-border bg-surface p-4'"
        @submit.prevent="submit"
    >
        <div class="grid gap-3 sm:grid-cols-2">
            <FormField id="priced-client" label="Client" required :error="form.errors.client_id">
                <SelectMenu
                    id="priced-client"
                    v-model="form.client_id"
                    :options="clientOptions"
                    placeholder="Choose a client"
                    :invalid="Boolean(form.errors.client_id)"
                />
            </FormField>
            <FormField id="priced-title" label="Title" required :error="form.errors.title">
                <TextInput id="priced-title" v-model="form.title" :invalid="!!form.errors.title" required />
            </FormField>
            <FormField id="priced-tax" label="Tax %" :error="form.errors.tax_rate" hint="Leave at 0 when tax does not apply.">
                <TextInput id="priced-tax" v-model="form.tax_rate" type="number" min="0" step="any" :invalid="!!form.errors.tax_rate" />
            </FormField>
        </div>

        <div class="space-y-2">
            <p class="text-[0.88rem] font-medium text-strong">Lines</p>
            <div v-for="(line, index) in form.lines" :key="index" class="grid items-start gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_8rem_auto]">
                <FormField :id="`line-desc-${index}`" label="Description" required :error="lineError(index, 'description')">
                    <TextInput :id="`line-desc-${index}`" v-model="line.description" :invalid="!!lineError(index, 'description')" required />
                </FormField>
                <FormField :id="`line-qty-${index}`" label="Qty" required :error="lineError(index, 'quantity')">
                    <TextInput :id="`line-qty-${index}`" v-model="line.quantity" type="number" min="0" step="1" :invalid="!!lineError(index, 'quantity')" required />
                </FormField>
                <FormField :id="`line-price-${index}`" label="Price" required :error="lineError(index, 'unit_price')">
                    <TextInput :id="`line-price-${index}`" v-model="line.unit_price" type="number" min="0" step="any" :invalid="!!lineError(index, 'unit_price')" required />
                </FormField>
                <div>
                    <span class="invisible block text-[0.8125rem] font-medium" aria-hidden="true">Remove</span>
                    <div class="mt-1.5">
                    <Button
                        type="button"
                        variant="danger"
                        size="icon"
                        class="size-11"
                        :disabled="form.lines.length === 1"
                        :aria-label="`Remove line ${index + 1}`"
                        @click="removeLine(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                    </div>
                </div>
            </div>
            <Button type="button" variant="secondary" size="sm" @click="addLine">Add a line</Button>
        </div>

        <FormField id="priced-notes" label="Notes" :error="form.errors.notes">
            <TextArea id="priced-notes" v-model="form.notes" :invalid="!!form.errors.notes" />
        </FormField>

        <Button type="submit" :loading="form.processing">{{ submitLabel }}</Button>
    </form>
</template>
