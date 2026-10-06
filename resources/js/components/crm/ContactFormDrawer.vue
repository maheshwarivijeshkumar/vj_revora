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
    countryCode,
    email as emailRule,
    maxLength,
    phone as phoneRule,
    useFormValidation,
} from '@/lib/validation';

export type EditableContact = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    country: string | null;
    owner_id: number | null;
    company_id: number | null;
    company_role: string | null;
};

const props = defineProps<{
    open: boolean;
    contact: EditableContact | null;
    options?: { owners: SelectOption[]; companies: SelectOption[] };
}>();

const emit = defineEmits<{ close: [] }>();

const isEdit = computed(() => props.contact !== null);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    job_title: '',
    country: '',
    owner_id: '',
    company_id: '',
    company_name: '',
    company_role: '',
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
        first_name: [maxLength(255, 'First name')],
        last_name: [maxLength(255, 'Last name')],
        email: [emailRule(), maxLength(255, 'Email')],
        phone: [phoneRule()],
        job_title: [maxLength(255, 'Job title')],
        country: [countryCode()],
        company_name: [maxLength(255, 'Company')],
        company_role: [maxLength(120, 'Role')],
    },
    {
        labels: {
            first_name: 'First name',
            last_name: 'Last name',
            email: 'Email',
            phone: 'Phone',
            job_title: 'Job title',
            country: 'Country',
            owner_id: 'Owner',
            company_id: 'Existing company',
            company_name: 'New company',
            company_role: 'Role there',
        },
        ids: {
            first_name: 'contact-first-name',
            last_name: 'contact-last-name',
            email: 'contact-email',
            phone: 'contact-phone',
            job_title: 'contact-job-title',
            country: 'contact-country',
            owner_id: 'contact-owner',
            company_id: 'contact-company',
            company_name: 'contact-company-name',
            company_role: 'contact-company-role',
        },
        groups: [
            {
                fields: ['email', 'phone'],
                check: (f) => f.email.trim() !== '' || f.phone.trim() !== '',
                message:
                    'Give an email address or a phone number so this person can be reached.',
            },
            {
                // The server refuses both; saying so here saves the round trip
                // and points at the field to clear.
                fields: ['company_name', 'company_id'],
                check: (f) =>
                    f.company_id === '' || f.company_name.trim() === '',
                message:
                    'Either choose an existing company or name a new one, not both.',
            },
        ],
    },
);

const ownerOptions = computed<SelectOption[]>(() => [
    { value: '', label: 'Nobody yet' },
    ...(props.options?.owners ?? []),
]);

const companyOptions = computed<SelectOption[]>(() => [
    { value: '', label: 'No company' },
    ...(props.options?.companies ?? []),
]);

/**
 * Picking an existing company and typing a new one are mutually exclusive: the
 * server refuses both, so the form stops offering the second once the first is
 * used rather than letting someone fill in a field that will be rejected.
 */
const companyChosen = computed(() => form.company_id !== '');
const companyTyped = computed(() => form.company_name.trim() !== '');
const hasCompany = computed(() => companyChosen.value || companyTyped.value);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        reset();
        form.clearErrors();

        const contact = props.contact;

        form.defaults({
            first_name: contact?.first_name ?? '',
            last_name: contact?.last_name ?? '',
            email: contact?.email ?? '',
            phone: contact?.phone ?? '',
            job_title: contact?.job_title ?? '',
            country: contact?.country ?? '',
            owner_id: contact?.owner_id ? String(contact.owner_id) : '',
            company_id: contact?.company_id ? String(contact.company_id) : '',
            company_name: '',
            company_role: contact?.company_role ?? '',
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

    if (props.contact) {
        form.patch(`/contacts/${props.contact.id}`, options);

        return;
    }

    form.post('/contacts', options);
}
</script>

<template>
    <Drawer
        :open="open"
        :title="isEdit ? 'Edit contact' : 'New contact'"
        :description="
            isEdit
                ? 'Changes are recorded in the audit trail.'
                : 'Someone already on file is enriched rather than duplicated.'
        "
        size="lg"
        :busy="form.processing"
        @close="emit('close')"
    >
        <form id="contact-form" novalidate @submit.prevent="submit">
            <FormErrorSummary :errors="summary" class="mb-4" />

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="contact-first-name"
                    label="First name"
                    :error="errors.first_name"
                >
                    <TextInput
                        id="contact-first-name"
                        v-model="form.first_name"
                        autocomplete="given-name"
                        :invalid="Boolean(errors.first_name)"
                        :settled="isSettled('first_name')"
                        @blur="touch('first_name')"
                        @update:model-value="revalidate('first_name')"
                    />
                </FormField>

                <FormField
                    id="contact-last-name"
                    label="Last name"
                    :error="errors.last_name"
                >
                    <TextInput
                        id="contact-last-name"
                        v-model="form.last_name"
                        autocomplete="family-name"
                        :invalid="Boolean(errors.last_name)"
                        :settled="isSettled('last_name')"
                        @blur="touch('last_name')"
                        @update:model-value="revalidate('last_name')"
                    />
                </FormField>

                <FormField
                    id="contact-email"
                    label="Email"
                    hint="Either an email or a phone number is needed."
                    :error="errors.email"
                >
                    <TextInput
                        id="contact-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        hint
                        :invalid="Boolean(errors.email)"
                        :settled="isSettled('email')"
                        @blur="touch('email')"
                        @update:model-value="revalidate('email')"
                    />
                </FormField>

                <FormField
                    id="contact-phone"
                    label="Phone"
                    :error="errors.phone"
                >
                    <TextInput
                        id="contact-phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        placeholder="+971 50 123 4567"
                        :invalid="Boolean(errors.phone)"
                        :settled="isSettled('phone')"
                        @blur="touch('phone')"
                        @update:model-value="revalidate('phone')"
                    />
                </FormField>

                <FormField
                    id="contact-job-title"
                    label="Job title"
                    :error="errors.job_title"
                >
                    <TextInput
                        id="contact-job-title"
                        v-model="form.job_title"
                        autocomplete="organization-title"
                        :invalid="Boolean(errors.job_title)"
                        :settled="isSettled('job_title')"
                        @blur="touch('job_title')"
                        @update:model-value="revalidate('job_title')"
                    />
                </FormField>

                <FormField
                    id="contact-country"
                    label="Country"
                    hint="Two-letter code, such as AE."
                    :error="errors.country"
                >
                    <TextInput
                        id="contact-country"
                        v-model="form.country"
                        autocomplete="country"
                        placeholder="AE"
                        :invalid="Boolean(errors.country)"
                        :settled="isSettled('country')"
                        @blur="touch('country')"
                        @update:model-value="revalidate('country')"
                    />
                </FormField>
            </div>

            <!-- Employment. §21: a role belongs to the relationship, not to the
                 person, so it only appears once a company is chosen. -->
            <fieldset class="mt-5 border-t border-border pt-5">
                <legend class="text-[0.88rem] font-medium text-strong">
                    Works at
                </legend>
                <p class="mt-1 text-[0.82rem] text-muted">
                    Choose a company already on file, or name a new one.
                </p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="contact-company"
                        label="Existing company"
                        optional-label
                        :error="errors.company_id"
                    >
                        <SelectMenu
                            id="contact-company"
                            v-model="form.company_id"
                            :options="companyOptions"
                            :disabled="companyTyped"
                            :invalid="Boolean(errors.company_id)"
                        />
                    </FormField>

                    <FormField
                        id="contact-company-name"
                        label="Or a new company"
                        optional-label
                        :error="errors.company_name"
                    >
                        <TextInput
                            id="contact-company-name"
                            v-model="form.company_name"
                            placeholder="Northwind Trading"
                            :disabled="companyChosen"
                            :invalid="Boolean(errors.company_name)"
                            :settled="isSettled('company_name')"
                            @blur="touch('company_name')"
                            @update:model-value="revalidate('company_name')"
                        />
                    </FormField>

                    <FormField
                        v-if="hasCompany"
                        id="contact-company-role"
                        label="Role there"
                        optional-label
                        :error="errors.company_role"
                    >
                        <TextInput
                            id="contact-company-role"
                            v-model="form.company_role"
                            placeholder="Procurement lead"
                            :invalid="Boolean(errors.company_role)"
                            :settled="isSettled('company_role')"
                            @blur="touch('company_role')"
                            @update:model-value="revalidate('company_role')"
                        />
                    </FormField>
                </div>
            </fieldset>

            <div
                class="mt-5 grid gap-4 border-t border-border pt-5 sm:grid-cols-2"
            >
                <FormField
                    id="contact-owner"
                    label="Owner"
                    optional-label
                    :error="errors.owner_id"
                >
                    <SelectMenu
                        id="contact-owner"
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
                form="contact-form"
                variant="brand"
                size="md"
                :loading="form.processing"
            >
                {{ isEdit ? 'Save changes' : 'Create contact' }}
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
