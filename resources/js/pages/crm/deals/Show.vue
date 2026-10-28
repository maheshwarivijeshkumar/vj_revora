<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Mail,
    Pencil,
    Phone,
    Trash2,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DocumentsPanel, {
    type AttachedDocument,
    type DocumentConstraints,
} from '@/components/crm/DocumentsPanel.vue';
import TimelinePanel, {
    type TimelineActivity,
    type TimelineNote,
    type TimelineTask,
} from '@/components/crm/TimelinePanel.vue';
import DealFormDrawer, {
    type EditableDeal,
} from '@/components/crm/DealFormDrawer.vue';
import Button from '@/components/ui/Button.vue';
import ScoreBadge from '@/components/ui/ScoreBadge.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

const { timeZone } = useClock();
const props = defineProps<{
    deal: {
        id: number;
        uuid: string;
        title: string;
        value: number;
        currency: string;
        probability: number;
        weighted_value: number;
        status: string;
        status_label: string;
        status_tone: string;
        is_open: boolean;
        pipeline: string | null;
        pipeline_id: number;
        stage: string | null;
        stage_id: number;
        owner: string | null;
        owner_id: number | null;
        company_id: number | null;
        contact_id: number | null;
        expected_close_date: string | null;
        closed_at: string | null;
        lost_reason: string | null;
        tags: { id: number; name: string }[];
        created_at: string | null;
    };
    stageHistory: {
        id: number;
        from: string | null;
        to: string | null;
        seconds_in_previous: number | null;
        changed_at: string | null;
    }[];
    related: {
        company: { id: number; name: string; domain: string | null } | null;
        contact: {
            id: number;
            name: string;
            email: string | null;
            phone: string | null;
        } | null;
        lead: { id: number; name: string; score: number } | null;
    };
    audit?: {
        id: number;
        action: string;
        actor: string;
        before: Record<string, unknown> | null;
        after: Record<string, unknown> | null;
        created_at: string | null;
    }[];
    documents: AttachedDocument[];
    documentConstraints?: DocumentConstraints;
    timelinePanel: {
        activities: TimelineActivity[];
        notes: TimelineNote[];
        tasks: TimelineTask[];
    };
    timelineOptions?: {
        activityTypes: {
            value: string;
            label: string;
            has_duration: boolean;
        }[];
        taskStatuses: { value: string; label: string; tone: string }[];
        taskPriorities: { value: string; label: string; tone: string }[];
        owners: SelectOption[];
    };
    options?: {
        owners: SelectOption[];
        companies: SelectOption[];
        contacts: SelectOption[];
        currencies: SelectOption[];
    };
}>();

const { can } = useAuthorization();

type Tab = 'overview' | 'activity' | 'files' | 'history' | 'audit';

const tab = ref<Tab>('overview');
const editing = ref(false);

const tabs = computed(() => [
    { id: 'overview' as Tab, label: 'Overview' },
    {
        id: 'activity' as Tab,
        label: `Activity (${props.timelinePanel.activities.length})`,
    },
    {
        id: 'files' as Tab,
        label: `Files (${props.documents.length})`,
    },
    {
        id: 'history' as Tab,
        label: `Stage history (${props.stageHistory.length})`,
    },
    ...(can('audit.view') ? [{ id: 'audit' as Tab, label: 'Audit' }] : []),
]);

const editable = computed<EditableDeal>(() => ({
    id: props.deal.id,
    title: props.deal.title,
    value: props.deal.value,
    currency: props.deal.currency,
    probability: props.deal.probability,
    expected_close_date: props.deal.expected_close_date,
    owner_id: props.deal.owner_id,
    company_id: props.deal.company_id,
    contact_id: props.deal.contact_id,
    lost_reason: props.deal.lost_reason,
    stage_name: props.deal.stage ?? '',
}));

function openTab(next: Tab): void {
    tab.value = next;

    // Both are deferred, so the first visit asks for them.
    if (next === 'audit' && props.audit === undefined) {
        router.reload({ only: ['audit'] });
    }

    if (next === 'activity' && props.timelineOptions === undefined) {
        router.reload({ only: ['timelineOptions'] });
    }

    if (next === 'files' && props.documentConstraints === undefined) {
        router.reload({ only: ['documentConstraints'] });
    }
}

function destroy(): void {
    if (
        !window.confirm(
            `Delete “${props.deal.title}” (${money(props.deal.value)})? It can be restored later.`,
        )
    ) {
        return;
    }

    router.delete(`/deals/${props.deal.id}`);
}

function money(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.deal.currency,
        maximumFractionDigits: 0,
    }).format(value);
}

/**
 * How long a stage held the deal, in the roughest unit that is still true.
 *
 * "3 days" rather than "3 days 4 hours 11 minutes": nobody reads a pipeline
 * history to the minute, and the precision implies a confidence the timestamps
 * do not have.
 */
function duration(seconds: number | null): string {
    if (seconds === null) {
        return '';
    }

    const days = Math.floor(seconds / 86400);

    if (days >= 1) {
        return `${days} ${days === 1 ? 'day' : 'days'}`;
    }

    const hours = Math.floor(seconds / 3600);

    if (hours >= 1) {
        return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
    }

    const minutes = Math.max(1, Math.floor(seconds / 60));

    return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
}

function formatDate(value: string | null, withTime = false): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, { timeZone: timeZone.value,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

function cell(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}
</script>

<template>
    <Head :title="deal.title" />

    <AppLayout
        :title="deal.title"
        :breadcrumbs="[
            { label: 'Deals', href: '/deals' },
            { label: deal.title },
        ]"
    >
        <template #actions>
            <Link
                href="/deals"
                class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-[0.88rem] text-muted transition-colors hover:bg-surface-alt hover:text-strong"
            >
                <ArrowLeft class="size-4" />
                Board
            </Link>
            <Button
                v-if="can('deal.update')"
                variant="primary"
                size="sm"
                @click="editing = true"
            >
                <Pencil class="size-4" />
                Edit
            </Button>
            <button
                v-if="can('deal.delete')"
                type="button"
                class="rounded-md p-2 text-danger transition-colors hover:bg-danger-soft"
                :aria-label="`Delete ${deal.title}`"
                @click="destroy"
            >
                <Trash2 class="size-4" />
            </button>
        </template>

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div class="min-w-0">
                <div
                    class="flex gap-1 overflow-x-auto border-b border-border"
                    role="tablist"
                >
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.id"
                        class="shrink-0 border-b-2 px-3 py-2 text-[0.9rem] font-medium transition-colors"
                        :class="
                            tab === item.id
                                ? 'border-primary-600 text-strong'
                                : 'border-transparent text-muted hover:text-strong'
                        "
                        @click="openTab(item.id)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <!-- Overview -->
                <div v-if="tab === 'overview'" class="mt-4 space-y-4">
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <div
                            class="flex flex-wrap items-baseline gap-x-6 gap-y-3"
                        >
                            <div>
                                <p class="text-[0.78rem] text-muted">Value</p>
                                <p
                                    class="text-[1.4rem] font-semibold text-strong tabular-nums"
                                >
                                    {{ money(deal.value) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[0.78rem] text-muted">
                                    Weighted at {{ deal.probability }}%
                                </p>
                                <p
                                    class="text-[1.4rem] font-semibold text-strong tabular-nums"
                                >
                                    {{ money(deal.weighted_value) }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="deal.value === 0"
                            class="mt-3 text-[0.85rem] text-muted"
                        >
                            No value yet — a deal often exists before its number
                            does.
                        </p>
                    </div>

                    <!-- Who it is with (§21) -->
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Who this is with
                        </h2>

                        <dl class="mt-3 space-y-3">
                            <div v-if="related.company">
                                <dt class="text-[0.78rem] text-muted">
                                    Company
                                </dt>
                                <dd class="mt-0.5">
                                    <Link
                                        :href="`/companies?search=${encodeURIComponent(related.company.name)}`"
                                        class="flex items-center gap-1.5 text-[0.92rem] text-strong hover:text-primary-600"
                                    >
                                        <Building2
                                            class="size-3.5 shrink-0 text-soft"
                                        />
                                        {{ related.company.name }}
                                    </Link>
                                </dd>
                            </div>

                            <div v-if="related.contact">
                                <dt class="text-[0.78rem] text-muted">
                                    Main contact
                                </dt>
                                <dd
                                    class="mt-0.5 flex flex-wrap items-center gap-x-4 gap-y-1"
                                >
                                    <span
                                        class="flex items-center gap-1.5 text-[0.92rem] text-strong"
                                    >
                                        <UserRound
                                            class="size-3.5 shrink-0 text-soft"
                                        />
                                        {{ related.contact.name }}
                                    </span>
                                    <a
                                        v-if="related.contact.email"
                                        :href="`mailto:${related.contact.email}`"
                                        class="flex items-center gap-1.5 text-[0.85rem] text-muted hover:text-primary-600"
                                    >
                                        <Mail class="size-3.5 shrink-0" />
                                        {{ related.contact.email }}
                                    </a>
                                    <a
                                        v-if="related.contact.phone"
                                        :href="`tel:${related.contact.phone}`"
                                        class="flex items-center gap-1.5 text-[0.85rem] text-muted hover:text-primary-600"
                                    >
                                        <Phone class="size-3.5 shrink-0" />
                                        {{ related.contact.phone }}
                                    </a>
                                </dd>
                            </div>

                            <!-- §85. The acquisition story survives conversion. -->
                            <div v-if="related.lead">
                                <dt class="text-[0.78rem] text-muted">
                                    Came from the lead
                                </dt>
                                <dd
                                    class="mt-0.5 flex items-center gap-2 text-[0.92rem]"
                                >
                                    <Link
                                        :href="`/leads/${related.lead.id}`"
                                        class="text-strong hover:text-primary-600"
                                    >
                                        {{ related.lead.name }}
                                    </Link>
                                    <ScoreBadge :score="related.lead.score" />
                                </dd>
                            </div>

                            <p
                                v-if="
                                    !related.company &&
                                    !related.contact &&
                                    !related.lead
                                "
                                class="text-[0.9rem] text-muted"
                            >
                                Nobody is linked to this deal yet. Edit it to
                                attach a company or a contact.
                            </p>
                        </dl>
                    </div>

                    <!-- Why it was lost, when it was -->
                    <div
                        v-if="deal.lost_reason"
                        class="rounded-xl border border-border bg-surface p-5"
                    >
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Why it was lost
                        </h2>
                        <p class="mt-2 text-[0.92rem] text-body">
                            {{ deal.lost_reason }}
                        </p>
                    </div>
                </div>

                <!-- What people did (§21) -->
                <TimelinePanel
                    v-else-if="tab === 'activity'"
                    class="mt-4"
                    subject-type="deal"
                    :subject-id="deal.id"
                    :activities="timelinePanel.activities"
                    :notes="timelinePanel.notes"
                    :tasks="timelinePanel.tasks"
                    :options="timelineOptions"
                />

                <!-- Files (§21) -->
                <DocumentsPanel
                    v-else-if="tab === 'files'"
                    class="mt-4"
                    subject-type="deal"
                    :subject-id="deal.id"
                    :documents="documents"
                    :constraints="documentConstraints"
                />

                <!-- Stage history (§22) -->
                <div v-else-if="tab === 'history'" class="mt-4">
                    <div
                        v-if="stageHistory.length === 0"
                        class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
                    >
                        This deal has not moved yet.
                    </div>

                    <ol v-else class="space-y-0">
                        <li
                            v-for="(change, index) in stageHistory"
                            :key="change.id"
                            class="relative flex gap-3 pb-4 pl-1"
                        >
                            <span
                                v-if="index < stageHistory.length - 1"
                                class="absolute top-5 left-[0.6rem] h-full w-px bg-border"
                                aria-hidden="true"
                            />
                            <span
                                class="relative z-10 mt-1 size-2.5 shrink-0 rounded-full bg-primary-500 ring-3 ring-[var(--color-page)]"
                                aria-hidden="true"
                            />

                            <div class="min-w-0 flex-1">
                                <p
                                    class="flex flex-wrap items-center gap-1.5 text-[0.9rem] font-medium text-strong"
                                >
                                    <template v-if="change.from">
                                        {{ change.from }}
                                        <ArrowRight
                                            class="size-3.5 text-soft"
                                            aria-hidden="true"
                                        />
                                    </template>
                                    <template v-else>Opened in</template>
                                    {{ change.to }}
                                </p>
                                <p
                                    class="text-[0.8rem] text-muted tabular-nums"
                                >
                                    {{ formatDate(change.changed_at, true) }}
                                    <template v-if="change.seconds_in_previous">
                                        · after
                                        {{
                                            duration(change.seconds_in_previous)
                                        }}
                                        in {{ change.from }}
                                    </template>
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Audit (§54) -->
                <div v-else class="mt-4">
                    <p
                        v-if="audit === undefined"
                        class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
                    >
                        Loading the audit trail…
                    </p>
                    <p
                        v-else-if="audit.length === 0"
                        class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
                    >
                        No recorded changes to this deal.
                    </p>
                    <ul
                        v-else
                        class="divide-y divide-border-soft overflow-hidden rounded-xl border border-border bg-surface"
                    >
                        <li
                            v-for="entry in audit"
                            :key="entry.id"
                            class="px-4 py-3"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="font-mono text-[0.8rem] text-strong"
                                >
                                    {{ entry.action }}
                                </span>
                                <span class="text-[0.85rem] text-muted">
                                    {{ entry.actor }}
                                </span>
                                <span
                                    class="text-[0.82rem] text-soft tabular-nums"
                                >
                                    {{ formatDate(entry.created_at, true) }}
                                </span>
                            </div>
                            <p
                                v-if="entry.after"
                                class="mt-1 text-[0.82rem] text-muted"
                            >
                                <template
                                    v-for="(value, key) in entry.after"
                                    :key="key"
                                >
                                    {{ key }}: {{ cell(entry.before?.[key]) }} →
                                    {{ cell(value) }}
                                    <br />
                                </template>
                            </p>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right rail (§44) -->
            <aside class="space-y-4">
                <div
                    class="space-y-3 rounded-xl border border-border bg-surface p-5"
                >
                    <div>
                        <p class="text-[0.78rem] text-muted">Status</p>
                        <div class="mt-1">
                            <StatusBadge
                                :tone="deal.status_tone as never"
                                :label="deal.status_label"
                            />
                        </div>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Stage</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ deal.stage }}
                        </p>
                        <!-- §22. A move recalculates probability, may close the
                             deal and reorders two columns, so the board owns it
                             and this page says where to do it. -->
                        <Link
                            v-if="deal.is_open && can('deal.update')"
                            href="/deals"
                            class="mt-1 inline-block text-[0.8rem] text-primary-700 underline dark:text-primary-300"
                        >
                            Move it on the board
                        </Link>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Pipeline</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ deal.pipeline }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Owner</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ deal.owner ?? 'Nobody yet' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Expected close</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ formatDate(deal.expected_close_date) }}
                        </p>
                    </div>

                    <div v-if="deal.closed_at">
                        <p class="text-[0.78rem] text-muted">Closed</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ formatDate(deal.closed_at, true) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Opened</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ formatDate(deal.created_at, true) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="deal.tags.length"
                    class="rounded-xl border border-border bg-surface p-5"
                >
                    <p class="text-[0.78rem] text-muted">Tags</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-for="tag in deal.tags"
                            :key="tag.id"
                            class="rounded bg-surface-alt px-2 py-0.5 text-[0.8rem] text-body"
                        >
                            {{ tag.name }}
                        </span>
                    </div>
                </div>
            </aside>
        </div>

        <DealFormDrawer
            :open="editing"
            :deal="editable"
            :pipeline-id="deal.pipeline_id"
            :options="options"
            @close="editing = false"
        />
    </AppLayout>
</template>
