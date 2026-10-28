<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CircleCheck, CircleX, TriangleAlert } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    account: {
        phone_number_id: string;
        business_account_id: string | null;
        display_phone_number: string | null;
        welcome_template: string | null;
        template_language: string;
        auto_send_welcome: boolean;
        status: string;
        status_label: string;
        status_tone: Tone;
        last_error: string | null;
    } | null;
    webhook: {
        url: string;
        verify_token_set: boolean;
        app_secret_set: boolean;
    };
}>();

const { can } = useAuthorization();

const form = useForm({
    phone_number_id: props.account?.phone_number_id ?? '',
    business_account_id: props.account?.business_account_id ?? '',
    display_phone_number: props.account?.display_phone_number ?? '',
    access_token: '',
    welcome_template: props.account?.welcome_template ?? '',
    template_language: props.account?.template_language ?? 'en',
    auto_send_welcome: props.account?.auto_send_welcome ?? true,
});

function save(): void {
    form.put('/settings/whatsapp', {
        preserveScroll: true,
        onSuccess: () => form.reset('access_token'),
    });
}

function disconnect(): void {
    if (window.confirm('Disconnect WhatsApp? Welcome messages will stop.')) {
        router.delete('/settings/whatsapp', { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="WhatsApp" />

    <AppLayout
        title="WhatsApp"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'WhatsApp' }]"
    >
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.9rem] text-muted">
                Connect your WhatsApp Business number through Meta's Cloud API.
                New leads who consented on their form get your approved welcome
                template automatically, and your team can chat with any lead
                from its page.
            </p>

            <section class="rounded-xl border border-border bg-surface p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-[0.98rem] font-semibold text-strong">
                        Business number
                    </h2>
                    <StatusBadge
                        v-if="account"
                        :tone="account.status_tone"
                        :label="account.status_label"
                    />
                </div>

                <p
                    v-if="account?.last_error"
                    class="mt-3 flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[0.83rem] text-danger"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    {{ account.last_error }}
                </p>

                <form class="mt-4" novalidate @submit.prevent="save">
                    <div class="grid items-start gap-x-4 gap-y-5 sm:grid-cols-2">
                        <FormField
                            id="wa-phone-id"
                            label="Phone number ID"
                            required
                            hint="Meta → WhatsApp → API Setup. Digits only, not the phone number itself."
                            :error="form.errors.phone_number_id"
                        >
                            <TextInput
                                id="wa-phone-id"
                                v-model="form.phone_number_id"
                                :invalid="Boolean(form.errors.phone_number_id)"
                            />
                        </FormField>
                        <FormField
                            id="wa-display"
                            label="Phone number"
                            optional-label
                            hint="As customers see it, for your reference."
                        >
                            <TextInput
                                id="wa-display"
                                v-model="form.display_phone_number"
                                type="tel"
                                placeholder="+92 300 1234567"
                            />
                        </FormField>
                        <FormField
                            id="wa-waba"
                            label="WhatsApp Business Account ID"
                            optional-label
                            :error="form.errors.business_account_id"
                        >
                            <TextInput
                                id="wa-waba"
                                v-model="form.business_account_id"
                            />
                        </FormField>
                        <FormField
                            id="wa-token"
                            label="Permanent access token"
                            :required="!account"
                            :hint="
                                account
                                    ? 'Saved and hidden. Leave blank to keep it.'
                                    : 'From a system user in Meta Business Settings.'
                            "
                            :error="form.errors.access_token"
                        >
                            <TextInput
                                id="wa-token"
                                v-model="form.access_token"
                                type="password"
                                autocomplete="off"
                                :invalid="Boolean(form.errors.access_token)"
                            />
                        </FormField>
                        <FormField
                            id="wa-template"
                            label="Welcome template name"
                            optional-label
                            hint="Exactly as approved in Meta, e.g. lead_welcome. {{1}} is filled with the lead's first name."
                            :error="form.errors.welcome_template"
                        >
                            <TextInput
                                id="wa-template"
                                v-model="form.welcome_template"
                                :invalid="Boolean(form.errors.welcome_template)"
                            />
                        </FormField>
                        <FormField
                            id="wa-lang"
                            label="Template language"
                            required
                            hint="e.g. en, en_US, ur"
                            :error="form.errors.template_language"
                        >
                            <TextInput
                                id="wa-lang"
                                v-model="form.template_language"
                            />
                        </FormField>
                    </div>

                    <label
                        class="mt-4 flex items-center gap-2 text-[0.88rem] text-strong"
                    >
                        <Checkbox
                            id="wa-auto"
                            v-model="form.auto_send_welcome"
                        />
                        Send the welcome template to every new lead who
                        consented
                    </label>

                    <div
                        v-if="can('integration.connect')"
                        class="mt-5 flex gap-2"
                    >
                        <Button
                            type="submit"
                            variant="brand"
                            size="lg"
                            :loading="form.processing"
                        >
                            {{ account ? 'Save' : 'Connect WhatsApp' }}
                        </Button>
                        <Button
                            v-if="account && can('integration.disconnect')"
                            variant="ghost"
                            size="lg"
                            @click="disconnect"
                        >
                            Disconnect
                        </Button>
                    </div>
                </form>
            </section>

            <!-- Webhook -->
            <section class="rounded-xl border border-border bg-surface p-5">
                <h2 class="text-[0.98rem] font-semibold text-strong">
                    Receiving replies
                </h2>
                <p class="mt-1 text-[0.85rem] text-muted">
                    In your Meta app → WhatsApp → Configuration, set this
                    callback URL, use the server's verify token, and subscribe
                    to the <code class="font-mono">messages</code> field.
                </p>
                <code
                    class="mt-3 block truncate rounded-lg border border-border bg-surface-alt px-3 py-2 font-mono text-[0.82rem] text-strong"
                >
                    {{ webhook.url }}
                </code>
                <ul class="mt-3 space-y-1 text-[0.85rem]">
                    <li class="flex items-center gap-2">
                        <component
                            :is="
                                webhook.verify_token_set ? CircleCheck : CircleX
                            "
                            class="size-4"
                            :class="
                                webhook.verify_token_set
                                    ? 'text-success'
                                    : 'text-danger'
                            "
                        />
                        Verify token
                        {{
                            webhook.verify_token_set
                                ? 'is set on the server'
                                : 'is missing: set WHATSAPP_VERIFY_TOKEN'
                        }}
                    </li>
                    <li class="flex items-center gap-2">
                        <component
                            :is="webhook.app_secret_set ? CircleCheck : CircleX"
                            class="size-4"
                            :class="
                                webhook.app_secret_set
                                    ? 'text-success'
                                    : 'text-danger'
                            "
                        />
                        App secret
                        {{
                            webhook.app_secret_set
                                ? 'is set on the server'
                                : 'is missing: set WHATSAPP_APP_SECRET'
                        }}
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
