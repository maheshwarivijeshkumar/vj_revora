<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Plus, TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToastStore } from '@/stores/toast';

type ApiKeyRow = {
    id: number;
    name: string;
    prefix: string;
    scopes: string[];
    created_by: string | null;
    created_at: string | null;
    last_used_at: string | null;
    expires_at: string | null;
    revoked_at: string | null;
    is_usable: boolean;
};

defineProps<{
    keys: ApiKeyRow[];
    scopes: { value: string; label: string }[];
    recentRequests: {
        id: number;
        key: string | null;
        method: string;
        path: string;
        status: number;
        duration_ms: number | null;
        created_at: string | null;
    }[];
}>();

const page = usePage();
const toasts = useToastStore();

const creating = ref(false);
const copied = ref(false);

const form = useForm({ name: '', scopes: [] as string[], expires_at: '' });

/**
 * The secret, available for exactly one render.
 *
 * Flashed by the server and never persisted anywhere it could be read again,
 * which is what §48 means by "never returned after creation".
 */
const newKey = computed(
    () =>
        (page.props.flash as Record<string, unknown> | undefined)?.newApiKey as
            | { name: string; token: string }
            | undefined,
);

watch(newKey, (value) => {
    if (value) {
        creating.value = false;
        form.reset();
    }
});

function submit(): void {
    form.post('/settings/api-keys', { preserveScroll: true });
}

function toggleScope(scope: string, checked: boolean): void {
    form.scopes = checked
        ? [...form.scopes, scope]
        : form.scopes.filter((s) => s !== scope);
}

async function copyToken(token: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(token);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        toasts.error('Could not copy', {
            description: 'Select the key and copy it manually.',
        });
    }
}

function revoke(key: ApiKeyRow): void {
    // Revoking cannot be undone, so the confirmation names the key (§113).
    if (
        !window.confirm(
            `Revoke “${key.name}”? Anything using it will stop working immediately.`,
        )
    ) {
        return;
    }

    router.delete(`/settings/api-keys/${key.id}`, { preserveScroll: true });
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="API keys" />

    <AppLayout
        title="API keys"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'API keys' }]"
    >
        <template #actions>
            <Button variant="primary" size="sm" @click="creating = !creating">
                <Plus class="size-4" />
                New key
            </Button>
        </template>

        <div class="space-y-4">
            <!-- The one and only sight of the secret. -->
            <div
                v-if="newKey"
                class="rounded-xl border-2 border-primary-500 bg-primary-50 p-5 dark:bg-primary-900/20"
            >
                <div class="flex items-start gap-3">
                    <KeyRound class="mt-0.5 size-5 shrink-0 text-primary-600" />
                    <div class="min-w-0 flex-1">
                        <h2 class="text-[0.98rem] font-semibold text-strong">
                            “{{ newKey.name }}” created
                        </h2>
                        <p class="mt-1 text-[0.9rem] text-muted">
                            Copy it now. We store only a hash, so this is the
                            only time it can be shown.
                        </p>

                        <div class="mt-3 flex items-center gap-2">
                            <code
                                class="min-w-0 flex-1 truncate rounded-lg border border-border bg-surface px-3 py-2 font-mono text-[0.85rem] text-strong"
                            >
                                {{ newKey.token }}
                            </code>
                            <Button
                                variant="secondary"
                                size="sm"
                                @click="copyToken(newKey.token)"
                            >
                                <component
                                    :is="copied ? Check : Copy"
                                    class="size-4"
                                />
                                {{ copied ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create form -->
            <form
                v-if="creating"
                class="rounded-xl border border-border bg-surface p-5"
                novalidate
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="key-name"
                        label="Name"
                        required
                        :error="form.errors.name"
                    >
                        <TextInput
                            id="key-name"
                            v-model="form.name"
                            placeholder="Zapier integration"
                            required
                            :invalid="Boolean(form.errors.name)"
                        />
                    </FormField>

                    <FormField
                        id="key-expires"
                        label="Expires"
                        optional-label
                        hint="Leave blank for a key that does not expire."
                        :error="form.errors.expires_at"
                    >
                        <TextInput
                            id="key-expires"
                            v-model="form.expires_at"
                            type="date"
                            :invalid="Boolean(form.errors.expires_at)"
                            hint
                        />
                    </FormField>
                </div>

                <fieldset class="mt-5">
                    <legend class="text-[0.88rem] font-medium text-strong">
                        Scopes
                        <span class="text-danger" aria-hidden="true">*</span>
                    </legend>
                    <p class="mt-1 text-[0.82rem] text-muted">
                        Grant only what the integration needs. A write scope
                        includes its matching read scope.
                    </p>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="scope in scopes"
                            :key="scope.value"
                            class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2 text-[0.88rem] hover:bg-surface-alt"
                        >
                            <Checkbox
                                :id="`scope-${scope.value}`"
                                :model-value="form.scopes.includes(scope.value)"
                                @update:model-value="
                                    (c) => toggleScope(scope.value, c)
                                "
                            />
                            <span class="min-w-0">
                                <span class="block text-strong">{{
                                    scope.label
                                }}</span>
                                <span
                                    class="block font-mono text-[0.75rem] text-muted"
                                >
                                    {{ scope.value }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <p
                        v-if="form.errors.scopes"
                        role="alert"
                        class="mt-2 text-[0.85rem] text-danger"
                    >
                        {{ form.errors.scopes }}
                    </p>
                </fieldset>

                <div class="mt-5 flex gap-2">
                    <Button
                        type="submit"
                        variant="brand"
                        size="lg"
                        :loading="form.processing"
                    >
                        Create key
                    </Button>
                    <Button variant="ghost" size="lg" @click="creating = false">
                        Cancel
                    </Button>
                </div>
            </form>

            <!-- Existing keys -->
            <div
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-border bg-surface-alt">
                            <th
                                class="px-4 py-2.5 text-[0.78rem] font-semibold text-muted"
                            >
                                Name
                            </th>
                            <th
                                class="px-4 py-2.5 text-[0.78rem] font-semibold text-muted"
                            >
                                Key
                            </th>
                            <th
                                class="hidden px-4 py-2.5 text-[0.78rem] font-semibold text-muted lg:table-cell"
                            >
                                Scopes
                            </th>
                            <th
                                class="hidden px-4 py-2.5 text-[0.78rem] font-semibold text-muted lg:table-cell"
                            >
                                Last used
                            </th>
                            <th
                                class="px-4 py-2.5 text-right text-[0.78rem] font-semibold text-muted"
                            >
                                Status
                            </th>
                            <th class="px-4 py-2.5">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="keys.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center text-[0.9rem] text-muted"
                            >
                                No API keys yet. Create one to start using the
                                REST API.
                            </td>
                        </tr>
                        <tr
                            v-for="key in keys"
                            :key="key.id"
                            class="border-b border-border-soft last:border-0"
                        >
                            <td
                                class="px-4 py-3 text-[0.9rem] font-medium text-strong"
                            >
                                {{ key.name }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[0.82rem] text-muted"
                            >
                                rvk_{{ key.prefix }}…
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <span class="flex flex-wrap gap-1">
                                    <span
                                        v-for="scope in key.scopes"
                                        :key="scope"
                                        class="rounded bg-surface-alt px-1.5 py-0.5 font-mono text-[0.72rem] text-muted"
                                    >
                                        {{ scope }}
                                    </span>
                                </span>
                            </td>
                            <td
                                class="hidden px-4 py-3 text-[0.85rem] text-muted lg:table-cell"
                            >
                                {{ formatDate(key.last_used_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <StatusBadge
                                    :tone="
                                        key.is_usable ? 'qualified' : 'archived'
                                    "
                                    :label="
                                        key.revoked_at
                                            ? 'Revoked'
                                            : key.is_usable
                                              ? 'Active'
                                              : 'Expired'
                                    "
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="key.is_usable"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-[0.85rem] text-danger transition-colors hover:bg-danger-soft"
                                    @click="revoke(key)"
                                >
                                    Revoke
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent traffic (§50) -->
            <div
                v-if="recentRequests.length"
                class="rounded-xl border border-border bg-surface"
            >
                <h2
                    class="border-b border-border px-4 py-3 text-[0.95rem] font-semibold text-strong"
                >
                    Recent API requests
                </h2>
                <ul class="divide-y divide-border-soft">
                    <li
                        v-for="request in recentRequests"
                        :key="request.id"
                        class="flex items-center gap-3 px-4 py-2.5 text-[0.85rem]"
                    >
                        <span
                            class="w-14 shrink-0 font-mono text-[0.75rem] font-semibold"
                            :class="
                                request.status < 400
                                    ? 'text-primary-600'
                                    : 'text-danger'
                            "
                        >
                            {{ request.method }}
                        </span>
                        <span
                            class="min-w-0 flex-1 truncate font-mono text-muted"
                        >
                            /{{ request.path }}
                        </span>
                        <span class="shrink-0 text-muted tabular-nums">
                            {{ request.status }}
                        </span>
                        <span
                            class="hidden w-16 shrink-0 text-right text-soft tabular-nums sm:block"
                        >
                            {{ request.duration_ms }}ms
                        </span>
                    </li>
                </ul>
            </div>

            <p class="flex items-start gap-2 text-[0.85rem] text-muted">
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                Treat API keys like passwords. Anyone holding one can act on
                your workspace within its scopes.
            </p>
        </div>
    </AppLayout>
</template>
