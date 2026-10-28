<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';

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
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-[0.95rem] font-semibold text-strong">
                Business number
            </h3>
            <StatusBadge
                v-if="account"
                :tone="account.status_tone"
                :label="account.status_label"
            />
        </div>
        <p class="mt-1 text-[0.85rem] text-muted">
            The phone number ID and permanent token come from Meta → WhatsApp →
            API Setup. Replies use the callback URL and verify token above.
            <template v-if="!webhook.verify_token_set || !webhook.app_secret_set">
                Save the missing app secret or verify token before Meta can
                call back.
            </template>
        </p>

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
                    hint="Digits only, not the phone number itself."
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
                    hint="Exactly as approved in Meta, e.g. lead_welcome."
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
                    <TextInput id="wa-lang" v-model="form.template_language" />
                </FormField>
            </div>

            <label class="mt-4 flex items-center gap-2 text-[0.88rem] text-strong">
                <Checkbox id="wa-auto" v-model="form.auto_send_welcome" />
                Send the welcome template to every new lead who consented
            </label>

            <div v-if="can('integration.connect')" class="mt-5 flex gap-2">
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
    </div>
</template>
