<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Facebook,
    Instagram,
    Linkedin,
    PlugZap,
    RefreshCw,
    Trash2,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import AppLayout from '@/layouts/AppLayout.vue';

type Run = {
    id: number;
    trigger: string;
    status: string;
    fetched: number;
    created: number;
    merged: number;
    skipped: number;
    error: string | null;
    started_at: string;
    finished_at: string | null;
    account?: string | null;
};

type Platform = {
    key: string;
    label: string;
    account_noun: string;
    configured: boolean;
    connect_url: string;
    callback_url: string;
};

type Connection = {
    id: string;
    platform: string;
    platform_label: string;
    name: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    last_synced_at: string | null;
    token_expires_at: string | null;
    last_error: string | null;
    source_id: number | null;
    last_run: Run | null;
};

const props = defineProps<{
    platforms: Platform[];
    connections: Connection[];
    recentRuns: Run[];
    lookbackDays: number;
}>();

const { can } = useAuthorization();

const connectionsByPlatform = computed(() => {
    const grouped: Record<string, Connection[]> = {};

    for (const connection of props.connections) {
        (grouped[connection.platform] ??= []).push(connection);
    }

    return grouped;
});

/** A full navigation: the provider's sign-in page is not an Inertia page. */
function connect(platform: Platform): void {
    window.location.href = platform.connect_url;
}

function syncNow(connection: Connection): void {
    router.post(
        `/settings/integrations/connections/${connection.id}/sync`,
        {},
        { preserveScroll: true },
    );
}

function disconnect(connection: Connection): void {
    if (
        !window.confirm(
            `Disconnect “${connection.name}”? New leads will stop arriving. Leads already imported are kept.`,
        )
    ) {
        return;
    }

    router.delete(`/settings/integrations/connections/${connection.id}`, {
        preserveScroll: true,
    });
}

function leadsHref(connection: Connection): string {
    return connection.source_id
        ? `/leads?source_id[]=${connection.source_id}`
        : '/leads';
}

function runTone(status: string): Tone {
    const tones: Record<string, Tone> = {
        succeeded: 'success',
        failed: 'danger',
        running: 'info',
    };

    return tones[status] ?? 'neutral';
}

function expiresSoon(connection: Connection): boolean {
    if (!connection.token_expires_at) {
        return false;
    }

    const days =
        (new Date(connection.token_expires_at).getTime() - Date.now()) /
        86_400_000;

    return days < 7;
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Integrations" />

    <AppLayout
        title="Integrations"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Integrations' }]"
    >
        <div class="space-y-4">
            <p class="max-w-3xl text-[0.9rem] text-muted">
                Connect your lead-ad accounts and new form submissions arrive on
                the Leads page every hour, already deduplicated, scored and
                assigned. The first sync imports the last
                {{ lookbackDays }} days. Only leads from your own ad forms are
                imported, through each network's official API.
            </p>

            <section
                v-for="platform in platforms"
                :key="platform.key"
                class="rounded-xl border border-border bg-surface p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-alt text-strong"
                            aria-hidden="true"
                        >
                            <template v-if="platform.key === 'meta'">
                                <Facebook class="size-5" />
                            </template>
                            <Linkedin v-else class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2
                                class="flex items-center gap-1.5 text-[0.98rem] font-semibold text-strong"
                            >
                                {{ platform.label }}
                                <Instagram
                                    v-if="platform.key === 'meta'"
                                    class="size-4 text-muted"
                                    aria-hidden="true"
                                />
                            </h2>
                            <p class="mt-0.5 text-[0.85rem] text-muted">
                                <template v-if="platform.key === 'meta'">
                                    Connect a Facebook Page. Its Facebook and
                                    Instagram lead ads both come through.
                                </template>
                                <template v-else>
                                    Connect a LinkedIn ad account to import its
                                    Lead Gen Form responses.
                                </template>
                            </p>
                        </div>
                    </div>

                    <Button
                        v-if="can('integration.connect') && platform.configured"
                        variant="primary"
                        size="sm"
                        @click="connect(platform)"
                    >
                        <PlugZap class="size-4" />
                        {{
                            connectionsByPlatform[platform.key]?.length
                                ? 'Connect another'
                                : 'Connect'
                        }}
                    </Button>
                </div>

                <div
                    v-if="!platform.configured"
                    class="mt-4 rounded-lg border border-dashed border-border px-4 py-3 text-[0.85rem] text-muted"
                >
                    <p class="font-medium text-strong">Not set up yet</p>
                    <p class="mt-1">
                        Add this network's app ID and secret to the server
                        environment, and register this redirect URL in the app's
                        settings:
                    </p>
                    <code
                        class="mt-2 block truncate rounded bg-surface-alt px-2 py-1 font-mono text-[0.8rem] text-strong"
                        >{{ platform.callback_url }}</code
                    >
                </div>

                <ul
                    v-if="connectionsByPlatform[platform.key]?.length"
                    class="mt-4 divide-y divide-border rounded-lg border border-border"
                >
                    <li
                        v-for="connection in connectionsByPlatform[
                            platform.key
                        ]"
                        :key="connection.id"
                        class="px-4 py-3"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="truncate text-[0.92rem] font-medium text-strong"
                                    >
                                        {{ connection.name }}
                                    </p>
                                    <StatusBadge
                                        :tone="connection.status_tone"
                                        :label="connection.status_label"
                                    />
                                </div>
                                <p class="mt-0.5 text-[0.8rem] text-muted">
                                    Last synced
                                    {{ formatDate(connection.last_synced_at) }}
                                    <template v-if="connection.last_run">
                                        · last run brought
                                        {{ connection.last_run.created }} new,
                                        {{ connection.last_run.merged }} updated
                                    </template>
                                    <template
                                        v-if="connection.token_expires_at"
                                    >
                                        · access expires
                                        {{
                                            formatDate(
                                                connection.token_expires_at,
                                            )
                                        }}
                                    </template>
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <a
                                    :href="leadsHref(connection)"
                                    class="text-[0.85rem] font-medium text-primary-600 hover:underline"
                                >
                                    View leads
                                </a>
                                <Button
                                    v-if="
                                        can('integration.connect') &&
                                        (connection.status ===
                                            'needs_reconnect' ||
                                            expiresSoon(connection))
                                    "
                                    variant="primary"
                                    size="sm"
                                    @click="connect(platform)"
                                >
                                    Reconnect
                                </Button>
                                <Button
                                    v-else-if="can('integration.sync')"
                                    variant="secondary"
                                    size="sm"
                                    @click="syncNow(connection)"
                                >
                                    <RefreshCw class="size-4" />
                                    Sync now
                                </Button>
                                <button
                                    v-if="can('integration.disconnect')"
                                    type="button"
                                    class="rounded-md p-1.5 text-danger transition-colors hover:bg-danger-soft"
                                    :aria-label="`Disconnect ${connection.name}`"
                                    @click="disconnect(connection)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <p
                            v-if="connection.last_error"
                            class="mt-2 flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[0.83rem] text-danger"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                            {{ connection.last_error }}
                        </p>
                    </li>
                </ul>
            </section>

            <!-- Sync log -->
            <section class="rounded-xl border border-border bg-surface">
                <h2
                    class="border-b border-border px-5 py-3 text-[0.95rem] font-semibold text-strong"
                >
                    Recent syncs
                </h2>
                <p
                    v-if="recentRuns.length === 0"
                    class="px-5 py-8 text-center text-[0.88rem] text-muted"
                >
                    Nothing has synced yet. Syncs run every hour once an account
                    is connected.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-[0.85rem]">
                        <thead class="text-[0.75rem] text-soft uppercase">
                            <tr>
                                <th class="px-5 py-2 font-semibold">Account</th>
                                <th class="px-3 py-2 font-semibold">Started</th>
                                <th class="px-3 py-2 font-semibold">Status</th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    New
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    Updated
                                </th>
                                <th class="px-5 py-2 text-right font-semibold">
                                    Skipped
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="run in recentRuns" :key="run.id">
                                <td class="px-5 py-2 text-strong">
                                    {{ run.account ?? '—' }}
                                    <span
                                        v-if="run.trigger === 'manual'"
                                        class="text-muted"
                                        >(manual)</span
                                    >
                                </td>
                                <td class="px-3 py-2 text-muted">
                                    {{ formatDate(run.started_at) }}
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge
                                        :tone="runTone(run.status)"
                                        :label="run.status"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ run.created }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ run.merged }}
                                </td>
                                <td class="px-5 py-2 text-right tabular-nums">
                                    {{ run.skipped }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
