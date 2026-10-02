<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Check } from 'lucide-vue-next';
import { ref } from 'vue';
import PageHero from '@/components/marketing/PageHero.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import {
    accepted,
    email,
    maxLength,
    minLength,
    oneOf,
    required,
    useFormValidation,
} from '@/lib/validation';

defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const sent = ref(false);

const topics: SelectOption[] = [
    {
        value: 'demo',
        label: 'Book a demo',
        note: 'See the product against your own channels',
    },
    {
        value: 'pricing',
        label: 'Pricing or plans',
        note: 'Limits, billing and what fits',
    },
    {
        value: 'technical',
        label: 'Technical or API',
        note: 'Integrations, webhooks, architecture',
    },
    {
        value: 'agency',
        label: 'Agency or reseller',
        note: 'Multi-workspace and white label',
    },
    {
        value: 'security',
        label: 'Security or compliance',
        note: 'Isolation, audit, procurement',
    },
    {
        value: 'partnership',
        label: 'Partnership',
        note: 'Integrations and co-selling',
    },
    { value: 'other', label: 'Something else' },
];

const TOPIC_VALUES = topics.map((topic) => topic.value);

const form = useForm({
    name: '',
    email: '',
    company: '',
    topic: 'demo',
    message: '',
    consent: false,
    // Honeypot. Hidden from people, irresistible to naive bots.
    website: '',
});

// Mirrors app/Http/Requests/Marketing/ContactRequest.php.
const v = useFormValidation(form, {
    name: [required('Name'), maxLength(120, 'Name')],
    email: [
        required('Email address'),
        email(),
        maxLength(255, 'Email address'),
    ],
    company: [maxLength(160, 'Company')],
    topic: [
        required('Topic'),
        oneOf(TOPIC_VALUES, 'Please choose one of the listed topics.'),
    ],
    message: [
        required('Message'),
        minLength(10, 'A sentence or two would help us reply usefully.'),
        maxLength(4000, 'Message'),
    ],
    consent: [accepted('Please confirm we can contact you about this.')],
});

function submit(): void {
    // Stop here on client-side failure so the invalid state is visible
    // immediately, but the server still validates everything on arrival.
    if (!v.validate()) {
        document
            .querySelector('[aria-invalid="true"]')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            v.reset();
            form.reset('name', 'email', 'company', 'message', 'consent');
        },
    });
}

const expectations = [
    {
        term: 'A reply from someone building it',
        description:
            'Not a sales development rep working from a script. You will talk to people who can answer technical questions.',
    },
    {
        term: 'Thirty minutes, not ninety',
        description:
            'Your channels, your questions, the actual product on screen. No slide deck, no discovery call before the demo.',
    },
    {
        term: 'A straight answer about fit',
        description:
            'If the platform is wrong for what you need, we will say so on the call rather than after you have paid.',
    },
];
</script>

<template>
    <Head>
        <title>Contact — {{ brand.name }}</title>
        <meta
            name="description"
            content="Book a demo, ask about pricing, or talk to us about the API."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Contact"
            title="Tell us what you are trying to fix"
            lede="The more specific you are about where leads are currently going wrong, the more useful the conversation will be. Vague demos help nobody."
        />

        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8 lg:py-16">
                <div class="grid gap-12 lg:grid-cols-[1.15fr_1fr] lg:gap-20">
                    <div>
                        <div
                            v-if="sent"
                            class="rounded-xl border border-border bg-surface-alt p-8"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-full bg-success-soft text-success-strong"
                            >
                                <Check class="size-5" />
                            </div>
                            <h2
                                class="mt-4 font-display text-[1.4rem] font-bold tracking-[-0.02em] text-strong"
                            >
                                Message received
                            </h2>
                            <p
                                class="mt-2 max-w-[48ch] leading-relaxed text-muted"
                            >
                                We will come back to you within one working day.
                                If it is urgent and you have our email, replying
                                directly reaches us faster.
                            </p>
                            <button
                                type="button"
                                class="mt-5 text-[0.92rem] font-medium text-strong underline decoration-border underline-offset-4"
                                @click="sent = false"
                            >
                                Send another
                            </button>
                        </div>

                        <form v-else novalidate @submit.prevent="submit">
                            <p class="mb-6 text-[0.85rem] text-muted">
                                Fields marked
                                <span class="text-danger" aria-hidden="true"
                                    >*</span
                                >
                                are required.
                            </p>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <FormField
                                    id="name"
                                    label="Name"
                                    required
                                    :error="v.errors.value.name"
                                >
                                    <TextInput
                                        id="name"
                                        v-model="form.name"
                                        autocomplete="name"
                                        required
                                        :invalid="Boolean(v.errors.value.name)"
                                        @blur="v.touch('name')"
                                        @update:model-value="
                                            v.revalidate('name')
                                        "
                                    />
                                </FormField>

                                <FormField
                                    id="email"
                                    label="Work email"
                                    required
                                    :error="v.errors.value.email"
                                >
                                    <TextInput
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="you@company.com"
                                        required
                                        :invalid="Boolean(v.errors.value.email)"
                                        @blur="v.touch('email')"
                                        @update:model-value="
                                            v.revalidate('email')
                                        "
                                    />
                                </FormField>

                                <FormField
                                    id="company"
                                    label="Company"
                                    optional-label
                                    :error="v.errors.value.company"
                                >
                                    <TextInput
                                        id="company"
                                        v-model="form.company"
                                        autocomplete="organization"
                                        :invalid="
                                            Boolean(v.errors.value.company)
                                        "
                                        @blur="v.touch('company')"
                                        @update:model-value="
                                            v.revalidate('company')
                                        "
                                    />
                                </FormField>

                                <FormField
                                    id="topic"
                                    label="What is this about"
                                    required
                                    :error="v.errors.value.topic"
                                >
                                    <SelectMenu
                                        id="topic"
                                        v-model="form.topic"
                                        :options="topics"
                                        required
                                        :invalid="Boolean(v.errors.value.topic)"
                                        search-placeholder="Search topics"
                                        @update:model-value="
                                            v.revalidate('topic')
                                        "
                                    />
                                </FormField>

                                <div class="sm:col-span-2">
                                    <FormField
                                        id="message"
                                        label="What is going wrong today?"
                                        required
                                        hint="Where leads come from, roughly how many a month, what happens to them now, and what you wish happened instead."
                                        :error="v.errors.value.message"
                                    >
                                        <TextArea
                                            id="message"
                                            v-model="form.message"
                                            :rows="5"
                                            :maxlength="4000"
                                            required
                                            hint
                                            :invalid="
                                                Boolean(v.errors.value.message)
                                            "
                                            @blur="v.touch('message')"
                                            @update:model-value="
                                                v.revalidate('message')
                                            "
                                        />
                                    </FormField>
                                </div>
                            </div>

                            <div class="mt-6">
                                <div class="flex items-start gap-2.5">
                                    <Checkbox
                                        id="consent"
                                        v-model="form.consent"
                                        required
                                        :invalid="
                                            Boolean(v.errors.value.consent)
                                        "
                                        @update:model-value="
                                            v.revalidate('consent')
                                        "
                                    />
                                    <label
                                        for="consent"
                                        class="cursor-pointer text-[0.88rem] leading-relaxed text-muted"
                                    >
                                        I am happy to be contacted about this
                                        enquiry.
                                        <span
                                            class="text-danger"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        <span
                                            class="mt-0.5 block text-[0.82rem] text-soft"
                                        >
                                            We will not add you to a marketing
                                            list.
                                        </span>
                                    </label>
                                </div>
                                <p
                                    v-if="v.errors.value.consent"
                                    id="consent-error"
                                    role="alert"
                                    class="mt-1.5 text-[0.85rem] text-danger"
                                >
                                    {{ v.errors.value.consent }}
                                </p>
                            </div>

                            <!-- Honeypot -->
                            <div
                                class="absolute h-0 w-0 overflow-hidden"
                                aria-hidden="true"
                            >
                                <label for="website"
                                    >Leave this field empty</label
                                >
                                <input
                                    id="website"
                                    v-model="form.website"
                                    type="text"
                                    tabindex="-1"
                                    autocomplete="off"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="brand"
                                size="lg"
                                class="mt-7"
                                :loading="form.processing"
                            >
                                Send message
                                <ArrowRight
                                    v-if="!form.processing"
                                    class="size-4"
                                />
                            </Button>
                        </form>
                    </div>

                    <aside>
                        <h2
                            class="text-[0.72rem] font-semibold tracking-[0.13em] text-soft uppercase"
                        >
                            What to expect
                        </h2>
                        <dl
                            class="mt-5 divide-y divide-border border-t border-border"
                        >
                            <div
                                v-for="item in expectations"
                                :key="item.term"
                                class="py-5"
                            >
                                <dt
                                    class="text-[0.98rem] font-semibold text-strong"
                                >
                                    {{ item.term }}
                                </dt>
                                <dd
                                    class="mt-1.5 text-[0.92rem] leading-relaxed text-muted"
                                >
                                    {{ item.description }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            v-if="contact.email || contact.phone"
                            class="mt-8 rounded-xl border border-border bg-surface-alt p-6"
                        >
                            <h3
                                class="text-[0.95rem] font-semibold text-strong"
                            >
                                Prefer to reach us directly
                            </h3>
                            <ul class="mt-3 space-y-2 text-[0.92rem]">
                                <li v-if="contact.email">
                                    <a
                                        :href="`mailto:${contact.email}`"
                                        class="text-body underline decoration-border underline-offset-4 hover:text-strong"
                                    >
                                        {{ contact.email }}
                                    </a>
                                </li>
                                <li v-if="contact.phone">
                                    <a
                                        :href="`tel:${contact.phone}`"
                                        class="text-body underline decoration-border underline-offset-4 hover:text-strong"
                                    >
                                        {{ contact.phone }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
