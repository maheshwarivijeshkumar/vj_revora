<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Drawer from '@/components/overlay/Drawer.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormErrorSummary from '@/components/ui/FormErrorSummary.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import TextInput from '@/components/ui/TextInput.vue';
import {
    countryCode,
    date as dateRule,
    email as emailRule,
    maxLength,
    phone as phoneRule,
    required,
    url as urlRule,
    useFormValidation,
} from '@/lib/validation';

/** The subset of a lead this form edits. Null means "create". */
export type EditableLead = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    company_name: string | null;
    job_title: string | null;
    website: string | null;
    country: string | null;
    status: string;
    owner_id: number | null;
    lead_source_id: number | null;
    next_follow_up_at: string | null;
    consent: boolean;
};

const props = defineProps<{
    open: boolean;
    /** The lead being edited, or null to create a new one. */
    lead: EditableLead | null;
    options?: {
        statuses: { value: string; label: string }[];
        owners: SelectOption[];
        sources: SelectOption[];
    };
}>();

const emit = defineEmits<{ close: [] }>();

const isEdit = computed(() => props.lead !== null);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company_name: '',
    job_title: '',
    website: '',
    country: '',
    status: 'new',
    owner_id: '',
    lead_source_id: '',
    next_follow_up_at: '',
    consent: false,
});

/**
 * Client-side checks mirror the server's, which is the only authority (§42).
 * They exist so a typo is caught before a round trip, not instead of one.
 */
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
        company_name: [maxLength(255, 'Company')],
        job_title: [maxLength(255, 'Job title')],
        website: [urlRule()],
        country: [countryCode()],
        status: [required('Status')],
        next_follow_up_at: [dateRule()],
    },
    {
        // Human labels for the summary: a list that says "lead_source_id"
        // helps nobody.
        labels: {
            first_name: 'First name',
            last_name: 'Last name',
            email: 'Email',
            phone: 'Phone',
            company_name: 'Company',
            job_title: 'Job title',
            website: 'Website',
            country: 'Country',
            status: 'Status',
            owner_id: 'Assigned to',
            lead_source_id: 'Source',
            next_follow_up_at: 'Next follow-up',
        },
        // Ids differ from field names because ids must be unique across the
        // page and field names are not.
        ids: {
            first_name: 'lead-first-name',
            last_name: 'lead-last-name',
            email: 'lead-email',
            phone: 'lead-phone',
            company_name: 'lead-company',
            job_title: 'lead-job-title',
            website: 'lead-website',
            country: 'lead-country',
            status: 'lead-status',
            owner_id: 'lead-owner',
            lead_source_id: 'lead-source',
            next_follow_up_at: 'lead-follow-up',
        },
        groups: [
            {
                // Belongs to the pair, not to either field: expressing it
                // per-field would report it twice.
                fields: ['email', 'phone'],
                check: (f) => f.email.trim() !== '' || f.phone.trim() !== '',
                message:
                    'Give an email address or a phone number so this person can be reached.',
            },
        ],
    },
);

const statusOptions = computed(() =>
    (props.options?.statuses ?? []).map((s) => ({
        value: s.value,
        label: s.label,
    })),
);

const ownerOptions = computed<SelectOption[]>(() => [
    { value: '', label: 'Nobody yet' },
    ...(props.options?.owners ?? []),
]);

const sourceOptions = computed<SelectOption[]>(() => [
    { value: '', label: 'Not recorded' },
    ...(props.options?.sources ?? []),
]);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        reset();
        form.clearErrors();

        const lead = props.lead;

        // An edit loads the record; a create starts blank with `new` selected,
        // which is the only status a lead that does not exist yet can have.
        form.defaults({
            first_name: lead?.first_name ?? '',
            last_name: lead?.last_name ?? '',
            email: lead?.email ?? '',
            phone: lead?.phone ?? '',
            company_name: lead?.company_name ?? '',
            job_title: lead?.job_title ?? '',
            website: lead?.website ?? '',
            country: lead?.country ?? '',
            status: lead?.status ?? 'new',
            owner_id: lead?.owner_id ? String(lead.owner_id) : '',
            lead_source_id: lead?.lead_source_id
                ? String(lead.lead_source_id)
                : '',
            next_follow_up_at: lead?.next_follow_up_at
                ? lead.next_follow_up_at.slice(0, 10)
                : '',
            consent: lead?.consent ?? false,
        });

        form.reset();

        // The selects need owners, sources and statuses, which the index defers
        // until something actually asks for them (Inertia v3).
        if (!props.options) {
            router.reload({ only: ['options'] });
        }
    },
);

function submit(): void {
    // Focused on failure: in a scrolling drawer the failing field is often
    // off-screen, and a submit that silently does nothing is indistinguishable
    // from a broken button (§59).
    if (!validateAndFocus()) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (props.lead) {
        form.patch(`/leads/${props.lead.id}`, options);

        return;
    }

    form.post('/leads', options);
}
</script>

<template>
    <Drawer
        :open="open"
        :title="isEdit ? 'Edit lead' : 'New lead'"
        :description="
            isEdit
                ? 'Changes are scored and recorded in the audit trail.'
                : 'A new lead is scored and routed as soon as it is saved.'
        "
        size="lg"
        :busy="form.processing"
        @close="emit('close')"
    >
        <!-- novalidate throughout: the native bubble cannot be styled, says
             something different in every browser, vanishes as you type and
             stops at the first field. -->
        <form id="lead-form" novalidate @submit.prevent="submit">
            <FormErrorSummary :errors="summary" class="mb-4" />

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="lead-first-name"
                    label="First name"
                    :error="errors.first_name"
                >
                    <TextInput
                        id="lead-first-name"
                        v-model="form.first_name"
                        autocomplete="given-name"
                        :invalid="Boolean(errors.first_name)"
                        :settled="isSettled('first_name')"
                        @blur="touch('first_name')"
                        @update:model-value="revalidate('first_name')"
                    />
                </FormField>

                <FormField
                    id="lead-last-name"
                    label="Last name"
                    :error="errors.last_name"
                >
                    <TextInput
                        id="lead-last-name"
                        v-model="form.last_name"
                        autocomplete="family-name"
                        :invalid="Boolean(errors.last_name)"
                        :settled="isSettled('last_name')"
                        @blur="touch('last_name')"
                        @update:model-value="revalidate('last_name')"
                    />
                </FormField>

                <FormField
                    id="lead-email"
                    label="Email"
                    hint="Either an email or a phone number is needed."
                    :error="errors.email"
                >
                    <TextInput
                        id="lead-email"
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

                <FormField id="lead-phone" label="Phone" :error="errors.phone">
                    <TextInput
                        id="lead-phone"
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
                    id="lead-company"
                    label="Company"
                    :error="errors.company_name"
                >
                    <TextInput
                        id="lead-company"
                        v-model="form.company_name"
                        autocomplete="organization"
                        :invalid="Boolean(errors.company_name)"
                        :settled="isSettled('company_name')"
                        @blur="touch('company_name')"
                        @update:model-value="revalidate('company_name')"
                    />
                </FormField>

                <FormField
                    id="lead-job-title"
                    label="Job title"
                    :error="errors.job_title"
                >
                    <TextInput
                        id="lead-job-title"
                        v-model="form.job_title"
                        autocomplete="organization-title"
                        :invalid="Boolean(errors.job_title)"
                        :settled="isSettled('job_title')"
                        @blur="touch('job_title')"
                        @update:model-value="revalidate('job_title')"
                    />
                </FormField>

                <FormField
                    id="lead-website"
                    label="Website"
                    :error="errors.website"
                >
                    <TextInput
                        id="lead-website"
                        v-model="form.website"
                        type="url"
                        placeholder="https://example.com"
                        :invalid="Boolean(errors.website)"
                        :settled="isSettled('website')"
                        @blur="touch('website')"
                        @update:model-value="revalidate('website')"
                    />
                </FormField>

                <FormField
                    id="lead-country"
                    label="Country"
                    hint="Two-letter code, such as AE."
                    :error="errors.country"
                >
                    <TextInput
                        id="lead-country"
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

            <div
                class="mt-5 grid gap-4 border-t border-border pt-5 sm:grid-cols-2"
            >
                <FormField
                    id="lead-status"
                    label="Status"
                    required
                    :error="errors.status"
                >
                    <SelectMenu
                        id="lead-status"
                        v-model="form.status"
                        :options="statusOptions"
                        required
                        :invalid="Boolean(errors.status)"
                    />
                </FormField>

                <FormField
                    id="lead-owner"
                    label="Assigned to"
                    optional-label
                    :error="errors.owner_id"
                >
                    <SelectMenu
                        id="lead-owner"
                        v-model="form.owner_id"
                        :options="ownerOptions"
                        :invalid="Boolean(errors.owner_id)"
                    />
                </FormField>

                <FormField
                    id="lead-source"
                    label="Source"
                    optional-label
                    :error="errors.lead_source_id"
                >
                    <SelectMenu
                        id="lead-source"
                        v-model="form.lead_source_id"
                        :options="sourceOptions"
                        :invalid="Boolean(errors.lead_source_id)"
                    />
                </FormField>

                <FormField
                    id="lead-follow-up"
                    label="Next follow-up"
                    optional-label
                    :error="errors.next_follow_up_at"
                >
                    <TextInput
                        id="lead-follow-up"
                        v-model="form.next_follow_up_at"
                        type="date"
                        :invalid="Boolean(errors.next_follow_up_at)"
                        :settled="isSettled('next_follow_up_at')"
                        @blur="touch('next_follow_up_at')"
                        @update:model-value="revalidate('next_follow_up_at')"
                    />
                </FormField>
            </div>

            <!-- §88. Consent is a fact about the person, not a setting, so it
                 says what it means rather than "subscribe". -->
            <label
                class="mt-5 flex cursor-pointer items-start gap-2.5 border-t border-border pt-5 text-[0.88rem]"
            >
                <Checkbox id="lead-consent" v-model="form.consent" />
                <span>
                    <span class="block text-strong">
                        This person agreed to be contacted
                    </span>
                    <span class="block text-[0.82rem] text-muted">
                        Recorded with the date, so the agreement can be
                        evidenced later.
                    </span>
                </span>
            </label>
        </form>

        <template #footer>
            <Button
                type="submit"
                form="lead-form"
                variant="brand"
                size="md"
                :loading="form.processing"
            >
                {{ isEdit ? 'Save changes' : 'Create lead' }}
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
