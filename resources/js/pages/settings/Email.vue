<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Mail,
    Plus,
    RefreshCw,
    Star,
    Trash2,
    TriangleAlert,
} from 'lucide-vue-next';
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import AppLayout from '@/layouts/AppLayout.vue';

const { timeZone } = useClock();
type Account = {
    id: string;
    email: string;
    from_name: string | null;
    provider: string;
    provider_label: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    is_default: boolean;
    last_synced_at: string | null;
    last_error: string | null;
};

type OAuthProvider = {
    key: string;
    label: string;
    configured: boolean;
    connect_url: string;
    callback_url: string;
};

defineProps<{ accounts: Account[]; oauth: OAuthProvider[] }>();

const { can } = useAuthorization();
const adding = ref(false);

const encryptions = [
    { value: 'ssl', label: 'SSL' },
    { value: 'tls', label: 'TLS (STARTTLS)' },
    { value: 'none', label: 'None' },
];

const form = useForm({
    email: '',
    from_name: '',
    username: '',
    password: '',
    smtp_host: '',
    smtp_port: '465',
    smtp_encryption: 'ssl',
    imap_host: '',
    imap_port: '993',
    imap_encryption: 'ssl',
});

/** Most hosts use the address as the login and one server for both. */
function prefill(): void {
    if (form.email && !form.username) {
        form.username = form.email;
    }

    const domain = form.email.split('@')[1];

    if (domain && !form.smtp_host) {
        form.smtp_host = `mail.${domain}`;
        form.imap_host = `mail.${domain}`;
    }
}

function submit(): void {
    form.post('/settings/email', {
        preserveScroll: true,
        onSuccess: () => {
            adding.value = false;
            form.reset();
        },
    });
}

function connect(provider: OAuthProvider): void {
    window.location.href = provider.connect_url;
}

function makeDefault(account: Account): void {
    router.patch(
        `/settings/email/accounts/${account.id}`,
        { is_default: true },
        { preserveScroll: true },
    );
}

function sync(account: Account): void {
    router.post(
        `/settings/email/accounts/${account.id}/sync`,
        {},
        { preserveScroll: true },
    );
}

function remove(account: Account): void {
    if (
        !window.confirm(
            `Disconnect ${account.email}? Follow-ups will stop going out from it.`,
        )
    ) {
        return;
    }

    router.delete(`/settings/email/accounts/${account.id}`, {
        preserveScroll: true,
    });
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, { timeZone: timeZone.value,
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : 'Never';
}
</script>

<template>
    <Head title="Email" />

    <AppLayout
        title="Email"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Email' }]"
    >
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.9rem] text-muted">
                Connect the mailbox follow-ups are sent from. Replies to it are
                read every five minutes and filed on the lead who sent them,
                which also stops their follow-up emails.
            </p>

            <!-- Connect -->
            <section
                v-if="can('integration.connect')"
                class="rounded-xl border border-border bg-surface p-5"
            >
                <h2 class="text-[0.98rem] font-semibold text-strong">
                    Connect a mailbox
                </h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <button
                        v-for="provider in oauth"
                        :key="provider.key"
                        type="button"
                        class="rounded-lg border border-border px-4 py-3 text-left transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!provider.configured"
                        @click="connect(provider)"
                    >
                        <span
                            class="block text-[0.9rem] font-medium text-strong"
                            >Sign in with {{ provider.label }}</span
                        >
                        <span
                            v-if="provider.configured"
                            class="mt-0.5 block text-[0.8rem] text-muted"
                        >
                            Sends and reads through the official API.
                        </span>
                        <span
                            v-else
                            class="mt-0.5 block text-[0.8rem] text-muted"
                        >
                            Not set up yet. Register this redirect URL, then add
                            the client ID and secret to the server:
                            <code
                                class="mt-1 block truncate font-mono text-[0.75rem] text-strong"
                                >{{ provider.callback_url }}</code
                            >
                        </span>
                    </button>

                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-3 text-left transition-colors hover:bg-surface-alt"
                        @click="adding = !adding"
                    >
                        <span
                            class="block text-[0.9rem] font-medium text-strong"
                            >SMTP + IMAP</span
                        >
                        <span class="mt-0.5 block text-[0.8rem] text-muted">
                            cPanel, Hostinger, Zoho, or Gmail and Outlook with
                            an app password.
                        </span>
                    </button>
                </div>

                <form
                    v-if="adding"
                    class="mt-5 border-t border-border pt-5"
                    novalidate
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="mail-email"
                            label="Email address"
                            required
                            :error="form.errors.email"
                        >
                            <TextInput
                                id="mail-email"
                                v-model="form.email"
                                type="email"
                                placeholder="sales@yourdomain.com"
                                :invalid="Boolean(form.errors.email)"
                                @blur="prefill"
                            />
                        </FormField>
                        <FormField
                            id="mail-name"
                            label="Sender name"
                            optional-label
                            hint="Shown as who the email is from."
                        >
                            <TextInput
                                id="mail-name"
                                v-model="form.from_name"
                                placeholder="Vijesh from Revora"
                            />
                        </FormField>
                        <FormField
                            id="mail-user"
                            label="Username"
                            required
                            hint="Usually the full email address."
                            :error="form.errors.username"
                        >
                            <TextInput
                                id="mail-user"
                                v-model="form.username"
                                :invalid="Boolean(form.errors.username)"
                            />
                        </FormField>
                        <FormField
                            id="mail-pass"
                            label="Password"
                            required
                            hint="For Gmail or Outlook, use an app password, not your normal one."
                            :error="form.errors.password"
                        >
                            <TextInput
                                id="mail-pass"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                :invalid="Boolean(form.errors.password)"
                            />
                        </FormField>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_7rem_10rem]">
                        <FormField
                            id="smtp-host"
                            label="Outgoing (SMTP) server"
                            required
                            :error="form.errors.smtp_host"
                        >
                            <TextInput
                                id="smtp-host"
                                v-model="form.smtp_host"
                                placeholder="mail.yourdomain.com"
                            />
                        </FormField>
                        <FormField
                            id="smtp-port"
                            label="Port"
                            required
                            :error="form.errors.smtp_port"
                        >
                            <TextInput
                                id="smtp-port"
                                v-model="form.smtp_port"
                                type="number"
                            />
                        </FormField>
                        <FormField id="smtp-enc" label="Security" required>
                            <SelectMenu
                                id="smtp-enc"
                                v-model="form.smtp_encryption"
                                :options="encryptions"
                            />
                        </FormField>
                        <FormField
                            id="imap-host"
                            label="Incoming (IMAP) server"
                            required
                            :error="form.errors.imap_host"
                        >
                            <TextInput
                                id="imap-host"
                                v-model="form.imap_host"
                                placeholder="mail.yourdomain.com"
                            />
                        </FormField>
                        <FormField
                            id="imap-port"
                            label="Port"
                            required
                            :error="form.errors.imap_port"
                        >
                            <TextInput
                                id="imap-port"
                                v-model="form.imap_port"
                                type="number"
                            />
                        </FormField>
                        <FormField id="imap-enc" label="Security" required>
                            <SelectMenu
                                id="imap-enc"
                                v-model="form.imap_encryption"
                                :options="encryptions"
                            />
                        </FormField>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <Button
                            type="submit"
                            variant="brand"
                            size="lg"
                            :loading="form.processing"
                            >Test and connect</Button
                        >
                        <Button
                            variant="ghost"
                            size="lg"
                            @click="adding = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </section>

            <!-- Connected -->
            <section class="rounded-xl border border-border bg-surface">
                <h2
                    class="border-b border-border px-5 py-3 text-[0.95rem] font-semibold text-strong"
                >
                    Connected mailboxes
                </h2>

                <div
                    v-if="accounts.length === 0"
                    class="px-5 py-10 text-center"
                >
                    <Mail class="mx-auto size-6 text-muted" />
                    <p class="mt-2 text-[0.92rem] font-medium text-strong">
                        No mailbox yet
                    </p>
                    <p class="mx-auto mt-1 max-w-md text-[0.85rem] text-muted">
                        Follow-up emails start once one is connected. New leads
                        are enrolled from then on.
                    </p>
                </div>

                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="account in accounts"
                        :key="account.id"
                        class="px-5 py-3"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="truncate text-[0.92rem] font-medium text-strong"
                                    >
                                        {{ account.email }}
                                    </p>
                                    <StatusBadge
                                        :tone="account.status_tone"
                                        :label="account.status_label"
                                    />
                                    <StatusBadge
                                        v-if="account.is_default"
                                        tone="info"
                                        label="Sends follow-ups"
                                    />
                                </div>
                                <p class="mt-0.5 text-[0.8rem] text-muted">
                                    {{ account.provider_label }}
                                    <template v-if="account.from_name">
                                        · as “{{ account.from_name }}”</template
                                    >
                                    · inbox checked
                                    {{ formatDate(account.last_synced_at) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <Button
                                    v-if="
                                        !account.is_default &&
                                        can('integration.connect')
                                    "
                                    variant="ghost"
                                    size="sm"
                                    @click="makeDefault(account)"
                                >
                                    <Star class="size-4" />
                                    Use for follow-ups
                                </Button>
                                <Button
                                    v-if="can('integration.sync')"
                                    variant="secondary"
                                    size="sm"
                                    @click="sync(account)"
                                >
                                    <RefreshCw class="size-4" />
                                    Check now
                                </Button>
                                <button
                                    v-if="can('integration.disconnect')"
                                    type="button"
                                    class="rounded-md p-1.5 text-danger transition-colors hover:bg-danger-soft"
                                    :aria-label="`Disconnect ${account.email}`"
                                    @click="remove(account)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="account.last_error"
                            class="mt-2 flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[0.83rem] text-danger"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                            {{ account.last_error }}
                        </p>
                    </li>
                </ul>
            </section>

            <p
                v-if="can('integration.connect')"
                class="text-[0.82rem] text-muted"
            >
                <Plus class="inline size-3.5" /> Templates and the follow-up
                schedule are under Settings → Follow-up.
            </p>
        </div>
    </AppLayout>
</template>
