<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { maxLength, required, useFormValidation } from '@/lib/validation';

export type EditableCompany = {
    id: number;
    name: string;
    domain: string | null;
    website: string | null;
    industry: string | null;
    size: string | null;
    country: string | null;
    phone: string | null;
    owner_id: number | null;
};

const props = defineProps<{
    open: boolean;
    company: EditableCompany | null;
    options?: { owners: SelectOption[]; industries: SelectOption[] };
}>();

const emit = defineEmits<{ close: [] }>();

const isEdit = computed(() => props.company !== null);

const form = useForm({
    name: '',
    domain: '',
    website: '',
    industry: '',
    size: '',
    country: '',
    phone: '',
    owner_id: '',
});

const { errors, touch, revalidate, validate, reset } = useFormValidation(form, {
    name: [required('Company name'), maxLength(255, 'Company name')],
    industry: [maxLength(120, 'Industry')],
});

const ownerOptions = computed<SelectOption[]>(() => [
    { value: '', label: 'Nobody yet' },
    ...(props.options?.owners ?? []),
]);

/**
 * Sizes as bands rather than a free-text number.
 *
 * "50-200" is what a rep knows; an exact headcount is what they would guess at,
 * and a guessed number reads as fact once it is in a field.
 */
const SIZES: SelectOption[] = [
    { value: '', label: 'Unknown' },
    { value: '1-10', label: '1–10 people' },
    { value: '11-50', label: '11–50 people' },
    { value: '51-200', label: '51–200 people' },
    { value: '201-500', label: '201–500 people' },
    { value: '501-1000', label: '501–1,000 people' },
    { value: '1000+', label: 'More than 1,000 people' },
];

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        reset();
        form.clearErrors();

        const company = props.company;

        form.defaults({
            name: company?.name ?? '',
            domain: company?.domain ?? '',
            website: company?.website ?? '',
            industry: company?.industry ?? '',
            size: company?.size ?? '',
            country: company?.country ?? '',
            phone: company?.phone ?? '',
            owner_id: company?.owner_id ? String(company.owner_id) : '',
        });

        form.reset();

        if (!props.options) {
            router.reload({ only: ['options'] });
        }
    },
);

function submit(): void {
    if (!validate()) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (props.company) {
        form.patch(`/companies/${props.company.id}`, options);

        return;
    }

    form.post('/companies', options);
}
</script>

<template>
    <Drawer
        :open="open"
        :title="isEdit ? 'Edit company' : 'New company'"
        :description="
            isEdit
                ? 'Changes are recorded in the audit trail.'
                : 'A company already on file is enriched rather than duplicated.'
        "
        size="lg"
        :busy="form.processing"
        @close="emit('close')"
    >
        <form id="company-form" novalidate @submit.prevent="submit">
            <FormField
                id="company-name"
                label="Company name"
                required
                :error="errors.name"
            >
                <TextInput
                    id="company-name"
                    v-model="form.name"
                    autocomplete="organization"
                    required
                    :invalid="Boolean(errors.name)"
                    @blur="touch('name')"
                    @update:model-value="revalidate('name')"
                />
            </FormField>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <FormField
                    id="company-website"
                    label="Website"
                    optional-label
                    hint="The domain is worked out from this."
                    :error="errors.website"
                >
                    <TextInput
                        id="company-website"
                        v-model="form.website"
                        type="url"
                        placeholder="https://northwind.example"
                        :invalid="Boolean(errors.website)"
                    />
                </FormField>

                <FormField
                    id="company-domain"
                    label="Domain"
                    optional-label
                    hint="Set this to override what the website implies."
                    :error="errors.domain"
                >
                    <TextInput
                        id="company-domain"
                        v-model="form.domain"
                        placeholder="northwind.example"
                        :invalid="Boolean(errors.domain)"
                    />
                </FormField>

                <FormField
                    id="company-industry"
                    label="Industry"
                    optional-label
                    :error="errors.industry"
                >
                    <TextInput
                        id="company-industry"
                        v-model="form.industry"
                        placeholder="Logistics"
                        :invalid="Boolean(errors.industry)"
                        @blur="touch('industry')"
                        @update:model-value="revalidate('industry')"
                    />
                </FormField>

                <FormField
                    id="company-size"
                    label="Size"
                    optional-label
                    :error="errors.size"
                >
                    <SelectMenu
                        id="company-size"
                        v-model="form.size"
                        :options="SIZES"
                        :invalid="Boolean(errors.size)"
                    />
                </FormField>

                <FormField
                    id="company-country"
                    label="Country"
                    optional-label
                    hint="Two-letter code, such as AE."
                    :error="errors.country"
                >
                    <TextInput
                        id="company-country"
                        v-model="form.country"
                        autocomplete="country"
                        placeholder="AE"
                        :invalid="Boolean(errors.country)"
                    />
                </FormField>

                <FormField
                    id="company-phone"
                    label="Phone"
                    optional-label
                    :error="errors.phone"
                >
                    <TextInput
                        id="company-phone"
                        v-model="form.phone"
                        type="tel"
                        placeholder="+971 4 123 4567"
                        :invalid="Boolean(errors.phone)"
                    />
                </FormField>
            </div>

            <div
                class="mt-5 grid gap-4 border-t border-border pt-5 sm:grid-cols-2"
            >
                <FormField
                    id="company-owner"
                    label="Owner"
                    optional-label
                    :error="errors.owner_id"
                >
                    <SelectMenu
                        id="company-owner"
                        v-model="form.owner_id"
                        :options="ownerOptions"
                        :invalid="Boolean(errors.owner_id)"
                    />
                </FormField>
            </div>
        </form>

        <template #footer>
            <Button
                type="submit"
                form="company-form"
                variant="brand"
                size="md"
                :loading="form.processing"
            >
                {{ isEdit ? 'Save changes' : 'Create company' }}
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
