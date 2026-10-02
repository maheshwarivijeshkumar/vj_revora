<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Eye,
    EyeOff,
    Plus,
    RotateCcw,
    Send,
    Trash2,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToastStore } from '@/stores/toast';

type Endpoint = {
    id: string;
    description: string;
    url: string;
    secret: string;
    events: string[];
    is_active: boolean;
    is_deliverable: boolean;
    consecutive_failures: number;
    disabled_at: string | null;
    disabled_reason: string | null;
    last_delivered_at: string | null;
    deliveries_count: number;
};

type Delivery = {
    id: string;
    endpoint: string | null;
    event: string;
    status: string;
    status_label: string;
    attempt: number;
    response_status: number | null;
    error: string | null;
    duration_ms: number | null;
    is_replay: boolean;
    can_replay: boolean;
    created_at: string | null;
};

const props = defineProps<{
    endpoints: Endpoint[];
    events: { value: string; label: string; group: string }[];
    deliveries: Delivery[];
    signature: { header: string; tolerance: number };
    failureLimit: number;
}>();

const toasts = useToastStore();

const creating = ref(false);
const revealed = ref<string | null>(null);
const copied = ref<string | null>(null);

const form = useForm({ description: '', url: '', events: [] as string[] });

/** Grouped so the picker reads as entities rather than one long list. */
const grouped = computed(() => {
    const groups = new Map<string, typeof props.events>();

    for (const event of props.events) {
        const existing = groups.get(event.group);

        if (existing) {
            existing.push(event);
        } else {
            groups.set(event.group, [event]);
        }
    }

    return [...groups.entries()].map(([name, events]) => ({ name, events }));
});

function submit(): void {
    form.post('/settings/webhooks', {
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
}

function toggleEvent(event: string, checked: boolean): void {
    form.events = checked
        ? [...form.events, event]
        : form.events.filter((e) => e !== event);
}

async function copySecret(endpoint: Endpoint): Promise<void> {
    try {
        await navigator.clipboard.writeText(endpoint.secret);
        copied.value = endpoint.id;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        toasts.error('Could not copy', {
            description: 'Reveal the secret and copy it manually.',
        });
    }
}

function togglePaused(endpoint: Endpoint): void {
    router.patch(
        `/settings/webhooks/${endpoint.id}`,
        { is_active: !endpoint.is_active },
        { preserveScroll: true },
    );
}

function sendTest(endpoint: Endpoint): void {
    router.post(
        `/settings/webhooks/${endpoint.id}/test`,
        {},
        { preserveScroll: true },
    );
}

function replay(delivery: Delivery): void {
    router.post(
        `/settings/webhooks/deliveries/${delivery.id}/replay`,
        {},
        { preserveScroll: true },
    );
}

function remove(endpoint: Endpoint): void {
    // Removal stops delivery for good, so the confirmation names the endpoint.
    if (
        !window.confirm(
            `Remove “${endpoint.description}”? It will stop receiving events immediately.`,
        )
    ) {
        return;
    }

    router.delete(`/settings/webhooks/${endpoint.id}`, {
        preserveScroll: true,
    });
}

/** Semantic tones rather than lead-stage ones: a delivery is not a pipeline. */
function statusTone(status: string): Tone {
    const tones: Record<string, Tone> = {
        delivered: 'success',
        failed: 'danger',
        retrying: 'warning',
        pending: 'info',
    };

    return tones[status] ?? 'neutral';
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
    <Head title="Webhooks" />

    <AppLayout
        title="Webhooks"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Webhooks' }]"
    >
        <template #actions>
            <Button variant="primary" size="sm" @click="creating = !creating">
                <Plus class="size-4" />
                Add endpoint
            </Button>
        </template>

        <div class="space-y-4">
            <!-- Create form -->
            <form
                v-if="creating"
                class="rounded-xl border border-border bg-surface p-5"
                novalidate
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="hook-description"
                        label="Description"
                        required
                        hint="What this endpoint is for, so the log is readable later."
                        :error="form.errors.description"
                    >
                        <TextInput
                            id="hook-description"
                            v-model="form.description"
                            placeholder="Warehouse CRM sync"
                            required
                            :invalid="Boolean(form.errors.description)"
                        />
                    </FormField>

                    <FormField
                        id="hook-url"
                        label="Endpoint URL"
                        required
                        hint="HTTPS only. Events carry personal data."
                        :error="form.errors.url"
                    >
                        <TextInput
                            id="hook-url"
                            v-model="form.url"
                            type="url"
                            placeholder="https://example.com/hooks/revora"
                            required
                            :invalid="Boolean(form.errors.url)"
                        />
                    </FormField>
                </div>

                <fieldset class="mt-5">
                    <legend class="text-[0.88rem] font-medium text-strong">
                        Events
                        <span class="text-danger" aria-hidden="true">*</span>
                        <span class="sr-only">(required)</span>
                    </legend>

                    <div class="mt-3 space-y-4">
                        <div v-for="group in grouped" :key="group.name">
                            <p
                                class="mb-2 text-[0.75rem] font-semibold tracking-wide text-soft uppercase"
                            >
                                {{ group.name }}
                            </p>
                            <div
                                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <label
                                    v-for="event in group.events"
                                    :key="event.value"
                                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2 text-[0.88rem] hover:bg-surface-alt"
                                >
                                    <Checkbox
                                        :id="`event-${event.value}`"
                                        :model-value="
                                            form.events.includes(event.value)
                                        "
                                        @update:model-value="
                                            (c) => toggleEvent(event.value, c)
                                        "
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-strong">{{
                                            event.label
                                        }}</span>
                                        <span
                                            class="block font-mono text-[0.75rem] text-muted"
                                        >
                                            {{ event.value }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.events"
                        role="alert"
                        class="mt-2 text-[0.85rem] text-danger"
                    >
                        {{ form.errors.events }}
                    </p>
                </fieldset>

                <div class="mt-5 flex gap-2">
                    <Button
                        type="submit"
                        variant="brand"
                        size="lg"
                        :loading="form.processing"
                    >
                        Add endpoint
                    </Button>
                    <Button variant="ghost" size="lg" @click="creating = false">
                        Cancel
                    </Button>
                </div>
            </form>

            <!-- Endpoints -->
            <div
                v-if="endpoints.length === 0"
                class="rounded-xl border border-border bg-surface px-4 py-12 text-center"
            >
                <p class="text-[0.95rem] font-medium text-strong">
                    No endpoints yet
                </p>
                <p class="mx-auto mt-1 max-w-md text-[0.88rem] text-muted">
                    Add one to have Revora post events to your own systems as
                    they happen, rather than polling the API for changes.
                </p>
            </div>

            <div
                v-for="endpoint in endpoints"
                :key="endpoint.id"
                class="rounded-xl border border-border bg-surface p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2
                                class="text-[0.98rem] font-semibold text-strong"
                            >
                                {{ endpoint.description }}
                            </h2>
                            <StatusBadge
                                :tone="
                                    endpoint.is_deliverable
                                        ? 'success'
                                        : 'neutral'
                                "
                                :label="
                                    endpoint.disabled_at
                                        ? 'Disabled'
                                        : endpoint.is_active
                                          ? 'Active'
                                          : 'Paused'
                                "
                            />
                        </div>
                        <p
                            class="mt-1 truncate font-mono text-[0.82rem] text-muted"
                        >
                            {{ endpoint.url }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            :disabled="!endpoint.is_deliverable"
                            @click="sendTest(endpoint)"
                        >
                            <Send class="size-4" />
                            Send test
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="togglePaused(endpoint)"
                        >
                            {{ endpoint.is_active ? 'Pause' : 'Resume' }}
                        </Button>
                        <button
                            type="button"
                            class="rounded-md p-1.5 text-danger transition-colors hover:bg-danger-soft"
                            :aria-label="`Remove ${endpoint.description}`"
                            @click="remove(endpoint)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <p
                    v-if="endpoint.disabled_reason"
                    class="mt-3 flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[0.85rem] text-danger"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    {{ endpoint.disabled_reason }} Resume it once the endpoint
                    is reachable again.
                </p>

                <!-- Signing secret -->
                <div class="mt-4">
                    <p class="text-[0.78rem] font-semibold text-muted">
                        Signing secret
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <code
                            class="min-w-0 flex-1 truncate rounded-lg border border-border bg-surface-alt px-3 py-2 font-mono text-[0.82rem] text-strong"
                        >
                            {{
                                revealed === endpoint.id
                                    ? endpoint.secret
                                    : '•'.repeat(32)
                            }}
                        </code>
                        <button
                            type="button"
                            class="rounded-md p-2 text-muted transition-colors hover:bg-surface-alt"
                            :aria-label="
                                revealed === endpoint.id
                                    ? 'Hide signing secret'
                                    : 'Reveal signing secret'
                            "
                            @click="
                                revealed =
                                    revealed === endpoint.id
                                        ? null
                                        : endpoint.id
                            "
                        >
                            <component
                                :is="revealed === endpoint.id ? EyeOff : Eye"
                                class="size-4"
                            />
                        </button>
                        <button
                            type="button"
                            class="rounded-md p-2 text-muted transition-colors hover:bg-surface-alt"
                            aria-label="Copy signing secret"
                            @click="copySecret(endpoint)"
                        >
                            <component
                                :is="copied === endpoint.id ? Check : Copy"
                                class="size-4"
                            />
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-1">
                    <span
                        v-for="event in endpoint.events"
                        :key="event"
                        class="rounded bg-surface-alt px-1.5 py-0.5 font-mono text-[0.72rem] text-muted"
                    >
                        {{ event }}
                    </span>
                </div>

                <p class="mt-3 text-[0.8rem] text-soft">
                    {{ endpoint.deliveries_count }} deliveries · last at
                    {{ formatDate(endpoint.last_delivered_at) }}
                    <template v-if="endpoint.consecutive_failures > 0">
                        · {{ endpoint.consecutive_failures }} of
                        {{ failureLimit }} consecutive failures
                    </template>
                </p>
            </div>

            <!-- How to verify -->
            <div class="rounded-xl border border-border bg-surface p-5">
                <h2 class="text-[0.95rem] font-semibold text-strong">
                    Verifying a request
                </h2>
                <p class="mt-1 text-[0.88rem] text-muted">
                    Every request carries
                    <code class="font-mono text-strong">{{
                        signature.header
                    }}</code>
                    as
                    <code class="font-mono text-strong"
                        >t=&lt;unix&gt;,v1=&lt;hex&gt;</code
                    >. Compute
                    <code class="font-mono text-strong"
                        >HMAC-SHA256("&lt;t&gt;.&lt;raw body&gt;")</code
                    >
                    with your signing secret and compare it in constant time.
                    Reject anything more than
                    {{ Math.round(signature.tolerance / 60) }} minutes old — the
                    timestamp is inside the signed string, so a captured request
                    cannot be made to look fresh.
                </p>
                <p class="mt-2 text-[0.88rem] text-muted">
                    Retries re-send the same
                    <code class="font-mono text-strong">X-Revora-Delivery</code
                    >, so deduplicate on it rather than assuming each request is
                    new.
                </p>
            </div>

            <!-- Delivery log -->
            <div
                v-if="deliveries.length"
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <h2
                    class="border-b border-border px-4 py-3 text-[0.95rem] font-semibold text-strong"
                >
                    Recent deliveries
                </h2>
                <ul class="divide-y divide-border-soft">
                    <li
                        v-for="delivery in deliveries"
                        :key="delivery.id"
                        class="flex flex-wrap items-center gap-3 px-4 py-2.5 text-[0.85rem]"
                    >
                        <StatusBadge
                            :tone="statusTone(delivery.status)"
                            :label="delivery.status_label"
                        />
                        <span class="font-mono text-[0.8rem] text-strong">
                            {{ delivery.event }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-muted">
                            {{ delivery.endpoint }}
                            <template v-if="delivery.is_replay">
                                · replay
                            </template>
                            <template v-if="delivery.error">
                                · {{ delivery.error }}
                            </template>
                        </span>
                        <span
                            v-if="delivery.response_status"
                            class="shrink-0 text-muted tabular-nums"
                        >
                            {{ delivery.response_status }}
                        </span>
                        <span
                            v-if="delivery.attempt > 1"
                            class="shrink-0 text-soft"
                        >
                            attempt {{ delivery.attempt }}
                        </span>
                        <span class="shrink-0 text-soft tabular-nums">
                            {{ formatDate(delivery.created_at) }}
                        </span>
                        <button
                            v-if="delivery.can_replay"
                            type="button"
                            class="flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-[0.82rem] text-muted transition-colors hover:bg-surface-alt"
                            @click="replay(delivery)"
                        >
                            <RotateCcw class="size-3.5" />
                            Replay
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
