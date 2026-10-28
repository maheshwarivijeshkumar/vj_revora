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
    if (props.credential.client_id_required && form.client_id.trim() === '') {
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

const needsKeys = computed(
    () => form.enabled && (!keysReady.value || !props.credential.enabled),
);

function toggle(): void {
    if (!props.canSave || form.processing) {
        return;
    }

    const next = !form.enabled;
    form.enabled = next;

    if (!next && props.credential.enabled) {
        save();
    }
}

function save(): void {
    if (form.enabled && !keysReady.value) {
        return;
    }

    form.put(`/settings/integrations/apps/${props.credential.provider}`, {
        preserveScroll: true,
        onSuccess: () => form.reset('client_secret', 'api_key'),
    });
}
</script>

<template>
    <div class="rounded-xl border border-border bg-surface px-5 py-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h3 class="text-[0.95rem] font-semibold text-strong">
                        {{ credential.label }}
                    </h3>
                    <span
                        v-if="!form.enabled"
                        class="text-[0.82rem] text-muted"
                    >
                        Off
                    </span>
                    <span
                        v-else-if="needsKeys"
                        class="inline-flex items-center gap-1.5 text-[0.82rem] text-warning"
                    >
                        <span
                            class="size-1.5 rounded-full bg-warning"
                            aria-hidden="true"
                        />
                        Enabled · needs keys
                    </span>
                    <span v-else class="text-[0.82rem] text-primary-400">
                        Enabled
                    </span>
                </div>
                <p class="mt-1 text-[0.85rem] leading-relaxed text-muted">
                    {{ credential.hint }}
                </p>
            </div>

            <button
                type="button"
                role="switch"
                class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50"
                :class="form.enabled ? 'bg-primary-600' : 'bg-border-strong'"
                :aria-checked="form.enabled"
                :aria-label="`${credential.label} ${form.enabled ? 'enabled' : 'off'}`"
                :disabled="!canSave || form.processing"
                @click="toggle"
            >
                <span
                    class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-transform"
                    :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                />
            </button>
        </div>

        <p v-if="!form.enabled" class="mt-3 text-[0.85rem] text-muted">
            Turned off. Saved keys are kept and used again when you switch it
            back on.
        </p>

        <div v-else class="mt-4 space-y-4 border-t border-border pt-4">
            <p
                v-if="credential.callback_url"
                class="text-[0.82rem] text-muted"
            >
                Redirect URL
                <code
                    class="mt-1 block truncate rounded-lg border border-border bg-surface-alt px-3 py-2 font-mono text-[0.8rem] text-strong"
                    >{{ credential.callback_url }}</code
                >
            </p>

            <div class="grid items-start gap-4">
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
                    :required="
                        credential.secret_required && !credential.secret_set
                    "
                    :hint="
                        credential.secret_set
                            ? 'Saved and hidden. Leave blank to keep it.'
                            : 'Required. Stored encrypted and not shown again.'
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
                    :required="
                        credential.api_key_required && !credential.api_key_set
                    "
                    :optional-label="!credential.api_key_required"
                    :hint="
                        credential.api_key_set
                            ? 'Saved and hidden. Leave blank to keep it.'
                            : credential.api_key_required
                              ? 'Required. Stored encrypted and not shown again.'
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

            <div v-if="canSave" class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    size="lg"
                    :disabled="!keysReady"
                    :loading="form.processing"
                    @click="save"
                >
                    Save
                </Button>
                <p v-if="!keysReady" class="text-[0.82rem] text-muted">
                    Enter every required key before saving.
                </p>
            </div>
        </div>

        <slot />
    </div>
</template>
