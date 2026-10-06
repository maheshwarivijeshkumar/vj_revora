<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
import FormErrorSummary from '@/components/ui/FormErrorSummary.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import TextInput from '@/components/ui/TextInput.vue';
import {
    between,
    date as dateRule,
    maxLength,
    minimum,
    required,
    useFormValidation,
} from '@/lib/validation';

export type EditableDeal = {
    id: number;
    title: string;
    value: number;
    currency: string;
    probability: number;
    expected_close_date: string | null;
    owner_id: number | null;
    company_id: number | null;
    contact_id: number | null;
    lost_reason: string | null;
    /** Shown rather than edited: moving a deal is the board's job. */
    stage_name: string;
};

const props = defineProps<{
    open: boolean;
    deal: EditableDeal | null;
    pipelineId: number;
    options?: {
        owners: SelectOption[];
        companies: SelectOption[];
        contacts: SelectOption[];
        currencies: SelectOption[];
    };
}>();

const emit = defineEmits<{ close: [] }>();

const isEdit = computed(() => props.deal !== null);

const form = useForm({
    title: '',
    value: '',
    currency: 'USD',
    pipeline_id: String(props.pipelineId),
    probability: '',
    expected_close_date: '',
    owner_id: '',
    company_id: '',
    contact_id: '',
    lost_reason: '',
});

const {
    errors,
    summary,
    touch,
    revalidate,
    validateAndFocus,
    isSettled,
    reset,
} = useFormValidation(
    form,
    {
        title: [required('Deal name'), maxLength(255, 'Deal name')],
        value: [minimum(0, 'Value')],
        currency: [required('Currency')],
        probability: [between(0, 100, 'Probability')],
        expected_close_date: [dateRule()],
        lost_reason: [maxLength(255, 'Reason')],
    },
    {
        labels: {
            title: 'Deal name',
            value: 'Value',
            currency: 'Currency',
            probability: 'Probability',
            expected_close_date: 'Expected close',
            owner_id: 'Owner',
            company_id: 'Company',
            contact_id: 'Main contact',
            lost_reason: 'Reason it was lost',
        },
        ids: {
            title: 'deal-title',
            value: 'deal-value',
            currency: 'deal-currency',
            probability: 'deal-probability',
            expected_close_date: 'deal-close-date',
            owner_id: 'deal-owner',
            company_id: 'deal-company',
            contact_id: 'deal-contact',
            lost_reason: 'deal-lost-reason',
        },
    },
);

function withEmpty(label: string, options?: SelectOption[]): SelectOption[] {
    return [{ value: '', label }, ...(options ?? [])];
}

const ownerOptions = computed(() =>
    withEmpty('Nobody yet', props.options?.owners),
);
const companyOptions = computed(() =>
    withEmpty('No company', props.options?.companies),
);
const contactOptions = computed(() =>
    withEmpty('No contact', props.options?.contacts),
);
const currencyOptions = computed(
    () => props.options?.currencies ?? [{ value: 'USD', label: 'USD' }],
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        reset();
        form.clearErrors();

        const deal = props.deal;

        form.defaults({
            title: deal?.title ?? '',
            // Blank rather than 0 on a new deal: a zero in the box reads as a
            // number somebody chose, and the server treats empty as not known
            // yet.
            value: deal ? String(deal.value) : '',
            currency: deal?.currency ?? 'USD',
            pipeline_id: String(props.pipelineId),
            probability: deal ? String(deal.probability) : '',
            expected_close_date: deal?.expected_close_date?.slice(0, 10) ?? '',
            owner_id: deal?.owner_id ? String(deal.owner_id) : '',
            company_id: deal?.company_id ? String(deal.company_id) : '',
            contact_id: deal?.contact_id ? String(deal.contact_id) : '',
            lost_reason: deal?.lost_reason ?? '',
        });

        form.reset();

        if (!props.options) {
            router.reload({ only: ['options'] });
        }
    },
);

function submit(): void {
    if (!validateAndFocus()) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (props.deal) {
        form.patch(`/deals/${props.deal.id}`, options);

        return;
    }

    form.post('/deals', options);
}
</script>

<template>
    <Drawer
        :open="open"
        :title="isEdit ? 'Edit deal' : 'New deal'"
        :description="
            isEdit
                ? 'Move it between stages on the board.'
                : 'It opens at the top of the first stage.'
        "
        size="lg"
        :busy="form.processing"
        @close="emit('close')"
    >
        <form id="deal-form" novalidate @submit.prevent="submit">
            <FormErrorSummary :errors="summary" class="mb-4" />

            <FormField
                id="deal-title"
                label="Deal name"
                required
                hint="What you will recognise on the board."
                :error="errors.title"
            >
                <TextInput
                    id="deal-title"
                    v-model="form.title"
                    placeholder="Northwind annual renewal"
                    required
                    :invalid="Boolean(errors.title)"
                    :settled="isSettled('title')"
                    @blur="touch('title')"
                    @update:model-value="revalidate('title')"
                />
            </FormField>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <FormField
                        id="deal-value"
                        label="Value"
                        optional-label
                        hint="Leave blank until it is known."
                        :error="errors.value"
                    >
                        <TextInput
                            id="deal-value"
                            v-model="form.value"
                            type="number"
                            placeholder="25000"
                            :invalid="Boolean(errors.value)"
                            :settled="isSettled('value')"
                            @blur="touch('value')"
                            @update:model-value="revalidate('value')"
                        />
                    </FormField>
                </div>

                <FormField
                    id="deal-currency"
                    label="Currency"
                    required
                    :error="errors.currency"
                >
                    <SelectMenu
                        id="deal-currency"
                        v-model="form.currency"
                        :options="currencyOptions"
                        required
                        :invalid="Boolean(errors.currency)"
                        :settled="isSettled('currency')"
                        @update:model-value="revalidate('currency')"
                    />
                </FormField>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <FormField
                    id="deal-close-date"
                    label="Expected close"
                    optional-label
                    :error="errors.expected_close_date"
                >
                    <TextInput
                        id="deal-close-date"
                        v-model="form.expected_close_date"
                        type="date"
                        :invalid="Boolean(errors.expected_close_date)"
                        :settled="isSettled('expected_close_date')"
                        @blur="touch('expected_close_date')"
                        @update:model-value="revalidate('expected_close_date')"
                    />
                </FormField>

                <FormField
                    id="deal-probability"
                    label="Probability"
                    optional-label
                    hint="Blank follows the stage's own figure."
                    :error="errors.probability"
                >
                    <TextInput
                        id="deal-probability"
                        v-model="form.probability"
                        type="number"
                        placeholder="40"
                        :invalid="Boolean(errors.probability)"
                        :settled="isSettled('probability')"
                        @blur="touch('probability')"
                        @update:model-value="revalidate('probability')"
                    />
                </FormField>
            </div>

            <!-- Who it is with (§21) -->
            <div
                class="mt-5 grid gap-4 border-t border-border pt-5 sm:grid-cols-2"
            >
                <FormField
                    id="deal-company"
                    label="Company"
                    optional-label
                    :error="errors.company_id"
                >
                    <SelectMenu
                        id="deal-company"
                        v-model="form.company_id"
                        :options="companyOptions"
                        :invalid="Boolean(errors.company_id)"
                        :settled="isSettled('company_id')"
                        @update:model-value="revalidate('company_id')"
                    />
                </FormField>

                <FormField
                    id="deal-contact"
                    label="Main contact"
                    optional-label
                    :error="errors.contact_id"
                >
                    <SelectMenu
                        id="deal-contact"
                        v-model="form.contact_id"
                        :options="contactOptions"
                        :invalid="Boolean(errors.contact_id)"
                        :settled="isSettled('contact_id')"
                        @update:model-value="revalidate('contact_id')"
                    />
                </FormField>

                <FormField
                    id="deal-owner"
                    label="Owner"
                    optional-label
                    :error="errors.owner_id"
                >
                    <SelectMenu
                        id="deal-owner"
                        v-model="form.owner_id"
                        :options="ownerOptions"
                        :invalid="Boolean(errors.owner_id)"
                        :settled="isSettled('owner_id')"
                        @update:model-value="revalidate('owner_id')"
                    />
                </FormField>
            </div>

            <!-- Stage is shown, not edited: a move recalculates probability,
                 may close the deal and reorders two columns, so the board owns
                 it (§22). -->
            <div
                v-if="isEdit"
                class="mt-5 flex flex-wrap items-center gap-2 border-t border-border pt-5 text-[0.88rem]"
            >
                <span class="text-muted">Currently in</span>
                <span class="font-medium text-strong">
                    {{ deal?.stage_name }}
                </span>
                <span class="text-soft">
                    — drag the card on the board to move it.
                </span>
            </div>

            <FormField
                v-if="isEdit && deal?.lost_reason !== null"
                id="deal-lost-reason"
                label="Reason it was lost"
                class="mt-4"
                :error="errors.lost_reason"
            >
                <TextInput
                    id="deal-lost-reason"
                    v-model="form.lost_reason"
                    :invalid="Boolean(errors.lost_reason)"
                    :settled="isSettled('lost_reason')"
                    @blur="touch('lost_reason')"
                    @update:model-value="revalidate('lost_reason')"
                />
            </FormField>
        </form>

        <template #footer>
            <Button
                type="submit"
                form="deal-form"
                variant="brand"
                size="md"
                :loading="form.processing"
            >
                {{ isEdit ? 'Save changes' : 'Open deal' }}
            </Button>
            <Button
                variant="ghost"
                size="md"
                :disabled="form.processing"
                @click="emit('close')"
            >
                Cancel
            </Button>
            <p
                v-if="form.isDirty && !form.processing"
                class="ml-auto text-[0.8rem] text-soft"
            >
                Unsaved changes
            </p>
        </template>
    </Drawer>
</template>
