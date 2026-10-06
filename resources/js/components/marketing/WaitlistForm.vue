<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Check } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import {
    accepted,
    email,
    maxLength,
    required,
    useFormValidation,
} from '@/lib/validation';
import { useToastStore } from '@/stores/toast';

withDefaults(defineProps<{ brandName: string; align?: 'left' | 'center' }>(), {
    align: 'left',
});

const toasts = useToastStore();
const submitted = ref(false);

const form = useForm({
    email: '',
    consent: false,
    landing_page: '',
    // Honeypot. Hidden from people, irresistible to naive bots.
    website: '',
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
});

// Mirrors app/Http/Requests/Marketing/WaitlistRequest.php.
const v = useFormValidation(form, {
    email: [
        required('Email address'),
        email(),
        maxLength(255, 'Email address'),
    ],
    consent: [accepted('Please confirm you would like launch updates.')],
});

onMounted(() => {
    // Attribution is captured at the moment of intent (§16). Reading it on
    // submit would lose it for anyone who scrolls or navigates in-page first.
    const params = new URLSearchParams(window.location.search);
    form.landing_page = window.location.href;
    form.utm_source = params.get('utm_source') ?? '';
    form.utm_medium = params.get('utm_medium') ?? '';
    form.utm_campaign = params.get('utm_campaign') ?? '';
});

function submit(): void {
    // Stop on client-side failure so the error is visible immediately; the
    // server still validates everything on arrival.
    if (!v.validateAndFocus()) {
        return;
    }

    form.post('/waitlist', {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            v.reset();
            form.reset('email', 'consent');
        },
        onError: (errors) => {
            if (errors.website) {
                toasts.error('Something went wrong. Please try again.');
            }
        },
    });
}
</script>

<template>
    <div :class="align === 'center' && 'mx-auto'" class="max-w-lg">
        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="submitted"
                key="done"
                class="flex items-start gap-3 rounded-xl border border-border bg-surface p-5 text-left shadow-card"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-success-soft text-success-strong"
                >
                    <Check class="size-4.5" />
                </div>
                <div>
                    <p class="font-medium text-body text-strong">
                        You're on the list.
                    </p>
                    <p class="mt-0.5 text-small text-muted">
                        We'll email you before launch. No newsletter, no noise,
                        just the invite.
                    </p>
                    <button
                        type="button"
                        class="mt-2 text-small font-medium text-primary-600 hover:underline"
                        @click="submitted = false"
                    >
                        Add another address
                    </button>
                </div>
            </div>

            <form v-else key="form" novalidate @submit.prevent="submit">
                <FormField
                    :id="`wl-email-${align}`"
                    label="Email address"
                    required
                    :error="v.errors.value.email"
                >
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <TextInput
                            :id="`wl-email-${align}`"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@company.com"
                            required
                            class="flex-1"
                            :invalid="Boolean(v.errors.value.email)"
                            @blur="v.touch('email')"
                            @update:model-value="v.revalidate('email')"
                        />
                        <Button
                            type="submit"
                            variant="brand"
                            size="lg"
                            :loading="form.processing"
                            class="shrink-0"
                        >
                            Request early access
                            <ArrowRight
                                v-if="!form.processing"
                                class="size-4"
                            />
                        </Button>
                    </div>
                </FormField>

                <div class="mt-3.5">
                    <div class="flex items-start gap-2.5">
                        <Checkbox
                            :id="`wl-consent-${align}`"
                            v-model="form.consent"
                            required
                            :invalid="Boolean(v.errors.value.consent)"
                            @update:model-value="v.revalidate('consent')"
                        />
                        <label
                            :for="`wl-consent-${align}`"
                            class="cursor-pointer text-left text-small leading-relaxed text-muted"
                        >
                            Email me once when {{ brandName }} launches.
                            <span class="text-danger" aria-hidden="true"
                                >*</span
                            >
                            No marketing list, unsubscribe any time.
                        </label>
                    </div>
                    <p
                        v-if="v.errors.value.consent"
                        :id="`wl-consent-${align}-error`"
                        role="alert"
                        class="mt-1.5 text-left text-small text-danger"
                    >
                        {{ v.errors.value.consent }}
                    </p>
                </div>

                <!-- Honeypot: hidden from people, not from bots. -->
                <div
                    class="absolute h-0 w-0 overflow-hidden"
                    aria-hidden="true"
                >
                    <label :for="`wl-website-${align}`"
                        >Leave this field empty</label
                    >
                    <input
                        :id="`wl-website-${align}`"
                        v-model="form.website"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>
            </form>
        </Transition>
    </div>
</template>
