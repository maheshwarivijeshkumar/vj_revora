<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';

const props = defineProps<{
    credential: {
        provider: string;
        label: string;
        enabled: boolean;
        client_id: string;
        client_id_label: string | null;
        client_id_required: boolean;
        secret_label: string | null;
        secret_required: boolean;
        secret_set: boolean;
        api_key_label: string | null;
        api_key_required: boolean;
        api_key_set: boolean;
        callback_url: string | null;
        hint: string;
    };
    canSave: boolean;
}>();

const form = useForm({
    enabled: props.credential.enabled,
    client_id: props.credential.client_id,
    client_secret: '',
    api_key: '',
});

const keysReady = computed(() => {
    if (
        props.credential.client_id_required &&
        form.client_id.trim() === ''
    ) {
        return false;
    }

    if (
        props.credential.secret_required &&
        !props.credential.secret_set &&
        form.client_secret.trim() === ''
    ) {
        return false;
    }

    if (
        props.credential.api_key_required &&
        !props.credential.api_key_set &&
        form.api_key.trim() === ''
    ) {
        return false;
    }

    return true;
});

const blocked = computed(() => form.enabled && !keysReady.value);

function save(): void {
    if (blocked.value) {
        return;
    }

    form.put(`/settings/integrations/apps/${props.credential.provider}`, {
        preserveScroll: true,
        onSuccess: () => form.reset('client_secret', 'api_key'),
    });
}
</script>

<template>
    <form class="mt-4" novalidate @submit.prevent="save">
        <fieldset>
            <legend class="text-[0.8125rem] font-medium text-strong">
                Status
            </legend>
            <div
                class="mt-1.5 inline-flex rounded-lg border border-border bg-surface-alt p-0.5"
                role="radiogroup"
                :aria-label="`${credential.label} status`"
            >
                <label
                    class="cursor-pointer rounded-md px-3 py-1.5 text-[0.85rem] font-medium"
                    :class="
                        form.enabled
                            ? 'text-muted'
                            : 'bg-surface text-strong shadow-sm'
                    "
                >
                    <input
                        v-model="form.enabled"
                        class="sr-only"
                        type="radio"
                        :name="`${credential.provider}-status`"
                        :value="false"
                    />
                    Disabled
                </label>
                <label
                    class="cursor-pointer rounded-md px-3 py-1.5 text-[0.85rem] font-medium"
                    :class="
                        form.enabled
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-muted'
                    "
                >
                    <input
                        v-model="form.enabled"
                        class="sr-only"
                        type="radio"
                        :name="`${credential.provider}-status`"
                        :value="true"
                    />
                    Enabled
                </label>
            </div>
        </fieldset>

        <template v-if="form.enabled">
            <p class="mt-4 text-[0.85rem] text-muted">{{ credential.hint }}</p>
            <code
                v-if="credential.callback_url"
                class="mt-2 block truncate rounded-lg border border-border bg-surface-alt px-3 py-2 font-mono text-[0.8rem] text-strong"
                >{{ credential.callback_url }}</code
            >

            <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    v-if="credential.client_id_label"
                    :id="`${credential.provider}-client-id`"
                    :label="credential.client_id_label"
                    :required="credential.client_id_required"
                    :error="form.errors.client_id"
                >
                    <TextInput
                        :id="`${credential.provider}-client-id`"
                        v-model="form.client_id"
                        :invalid="Boolean(form.errors.client_id)"
                        autocomplete="off"
                    />
                </FormField>
                <FormField
                    v-if="credential.secret_label"
                    :id="`${credential.provider}-secret`"
                    :label="credential.secret_label"
                    :required="credential.secret_required && !credential.secret_set"
                    :hint="
                        credential.secret_set
                            ? 'Saved and hidden. Leave blank to keep it.'
                            : 'Required to enable. Stored encrypted.'
                    "
                    :error="form.errors.client_secret"
                >
                    <TextInput
                        :id="`${credential.provider}-secret`"
                        v-model="form.client_secret"
                        type="password"
                        autocomplete="new-password"
                        :invalid="Boolean(form.errors.client_secret)"
                    />
                </FormField>
                <FormField
                    v-if="credential.api_key_label"
                    :id="`${credential.provider}-api-key`"
                    :label="credential.api_key_label"
                    :required="credential.api_key_required && !credential.api_key_set"
                    :optional-label="!credential.api_key_required"
                    :hint="
                        credential.api_key_set
                            ? 'Saved and hidden. Leave blank to keep it.'
                            : credential.api_key_required
                              ? 'Required to enable. Stored encrypted.'
                              : 'Optional. Stored encrypted and not shown again.'
                    "
                    :error="form.errors.api_key"
                >
                    <TextInput
                        :id="`${credential.provider}-api-key`"
                        v-model="form.api_key"
                        type="password"
                        autocomplete="new-password"
                        :invalid="Boolean(form.errors.api_key)"
                    />
                </FormField>
            </div>

            <p v-if="blocked" class="mt-3 text-[0.85rem] text-danger">
                Enter every required key before enabling.
            </p>
        </template>
        <p v-else class="mt-3 text-[0.85rem] text-muted">
            Disabled. This network stays off until you enable it and save the
            required keys.
        </p>

        <Button
            v-if="canSave"
            type="submit"
            class="mt-4"
            size="lg"
            :disabled="blocked"
            :loading="form.processing"
        >
            {{
                form.enabled
                    ? credential.enabled
                        ? 'Save'
                        : 'Enable'
                    : 'Disable'
            }}
        </Button>
    </form>
</template>
