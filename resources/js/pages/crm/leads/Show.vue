<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Check,
    Globe,
    Mail,
    Pencil,
    Phone,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LeadFormDrawer, {
    type EditableLead,
} from '@/components/crm/LeadFormDrawer.vue';
import Button from '@/components/ui/Button.vue';
import ScoreBadge from '@/components/ui/ScoreBadge.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Finding = { check: string; verdict: string; detail: string };

const props = defineProps<{
    lead: {
        id: number;
        uuid: string;
        name: string;
        first_name: string | null;
        last_name: string | null;
        email: string | null;
        phone: string | null;
        phone_e164: string | null;
        phone_type: string | null;
        phone_country: string | null;
        company_name: string | null;
        job_title: string | null;
        website: string | null;
        country: string | null;
        status: string;
        status_label: string;
        status_tone: string;
        owner: string | null;
        owner_id: number | null;
        lead_source_id: number | null;
        consent: boolean;
        consent_at: string | null;
        consent_source: string | null;
        tags: { id: number; name: string; color: string | null }[];
        next_follow_up_at: string | null;
        last_activity_at: string | null;
        qualified_at: string | null;
        created_at: string | null;
        merged_into: { id: number; name: string } | null;
    };
    score: {
        score: number;
        band: string;
        band_label: string;
        method: string | null;
        reasons: { label: string; points: number }[];
        scored_at: string | null;
    };
    verification: {
        status: string;
        label: string;
        tone: string;
        confidence: number | null;
        findings: Finding[];
        verified_at: string | null;
    };
    attribution: {
        source: { name: string; type: string; authorized_api: boolean } | null;
        utm: Record<string, string>;
        landing_page: string | null;
        referrer: string | null;
        first_touch: Record<string, unknown>;
        last_touch: Record<string, unknown>;
        external_system: string | null;
        external_record_id: string | null;
    };
    timeline: {
        id: number;
        type: string;
        label: string;
        provider: string | null;
        user: string | null;
        payload: Record<string, unknown> | null;
        occurred_at: string;
    }[];
    deals: {
        id: number;
        title: string;
        value: number;
        currency: string;
        status: string;
        stage: string | null;
        pipeline: string | null;
    }[];
    duplicates: {
        id: number;
        name: string;
        email: string | null;
        created_at: string | null;
    }[];
    audit?: {
        id: number;
        action: string;
        actor: string;
        before: Record<string, unknown> | null;
        after: Record<string, unknown> | null;
        ip: string | null;
        created_at: string | null;
    }[];
    options?: {
        statuses: { value: string; label: string }[];
        owners: SelectOption[];
        sources: SelectOption[];
    };
}>();

const { can } = useAuthorization();

type Tab = 'overview' | 'timeline' | 'deals' | 'source' | 'audit';

const tab = ref<Tab>('overview');
const editing = ref(false);
const expandedEvent = ref<number | null>(null);

/**
 * Only tabs with something behind them.
 *
 * §44 lists conversations, emails, WhatsApp, tasks, appointments, notes and
 * documents too. Those arrive with the modules that produce them — an empty tab
 * implies a feature exists, which is worse than its absence (§124).
 */
const tabs = computed(() =>
    [
        { id: 'overview' as Tab, label: 'Overview' },
        { id: 'timeline' as Tab, label: `Timeline (${props.timeline.length})` },
        { id: 'deals' as Tab, label: `Deals (${props.deals.length})` },
        { id: 'source' as Tab, label: 'Source' },
        ...(can('audit.view') ? [{ id: 'audit' as Tab, label: 'Audit' }] : []),
    ].filter(Boolean),
);

const editable = computed<EditableLead>(() => ({
    id: props.lead.id,
    first_name: props.lead.first_name,
    last_name: props.lead.last_name,
    email: props.lead.email,
    phone: props.lead.phone,
    company_name: props.lead.company_name,
    job_title: props.lead.job_title,
    website: props.lead.website,
    country: props.lead.country,
    status: props.lead.status,
    owner_id: props.lead.owner_id,
    lead_source_id: props.lead.lead_source_id,
    next_follow_up_at: props.lead.next_follow_up_at,
    consent: props.lead.consent,
}));

const problems = computed(() =>
    props.verification.findings.filter((f) => f.verdict !== 'pass'),
);

const utmEntries = computed(() => Object.entries(props.attribution.utm ?? {}));

function openTab(next: Tab): void {
    tab.value = next;

    // The audit tab is deferred, so the first visit asks for it.
    if (next === 'audit' && props.audit === undefined) {
        router.reload({ only: ['audit'] });
    }
}

function destroy(): void {
    if (
        !window.confirm(`Delete ${props.lead.name}? It can be restored later.`)
    ) {
        return;
    }

    router.delete(`/leads/${props.lead.id}`);
}

function money(value: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDate(value: string | null, withTime = false): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
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
    <Head :title="lead.name" />

    <AppLayout
        :title="lead.name"
        :breadcrumbs="[
            { label: 'Leads', href: '/leads' },
            { label: lead.name },
        ]"
    >
        <template #actions>
            <Button variant="ghost" size="sm" as="div">
                <Link href="/leads" class="flex items-center gap-1.5">
                    <ArrowLeft class="size-4" />
                    All leads
                </Link>
            </Button>
            <Button
                v-if="can('lead.update')"
                variant="primary"
                size="sm"
                @click="editing = true"
            >
                <Pencil class="size-4" />
                Edit
            </Button>
            <button
                v-if="can('lead.delete')"
                type="button"
                class="rounded-md p-2 text-danger transition-colors hover:bg-danger-soft"
                :aria-label="`Delete ${lead.name}`"
                @click="destroy"
            >
                <Trash2 class="size-4" />
            </button>
        </template>

        <!-- A merged lead is a tombstone. Saying so first stops anyone working
             a person who now lives under another record (§18). -->
        <div
            v-if="lead.merged_into"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-warning bg-warning-soft px-4 py-3 text-[0.9rem]"
        >
            <TriangleAlert class="size-4 shrink-0 text-warning" />
            <span class="text-strong">
                This lead was merged into another record.
            </span>
            <Link
                :href="`/leads/${lead.merged_into.id}`"
                class="font-medium text-primary-700 underline dark:text-primary-300"
            >
                Open {{ lead.merged_into.name }}
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <!-- Main column -->
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
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Contact details
                        </h2>
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-[0.78rem] text-muted">Email</dt>
                                <dd class="mt-0.5">
                                    <a
                                        v-if="lead.email"
                                        :href="`mailto:${lead.email}`"
                                        class="flex items-center gap-1.5 text-[0.92rem] text-strong hover:text-primary-600"
                                    >
                                        <Mail
                                            class="size-3.5 shrink-0 text-soft"
                                        />
                                        {{ lead.email }}
                                    </a>
                                    <span v-else class="text-soft">—</span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[0.78rem] text-muted">Phone</dt>
                                <dd class="mt-0.5">
                                    <!-- Dialled from the E.164 form, so the
                                         link works from any country. -->
                                    <a
                                        v-if="lead.phone_e164"
                                        :href="`tel:${lead.phone_e164}`"
                                        class="flex items-center gap-1.5 text-[0.92rem] text-strong hover:text-primary-600"
                                    >
                                        <Phone
                                            class="size-3.5 shrink-0 text-soft"
                                        />
                                        {{ lead.phone }}
                                    </a>
                                    <span
                                        v-else-if="lead.phone"
                                        class="text-[0.92rem] text-strong"
                                    >
                                        {{ lead.phone }}
                                    </span>
                                    <span v-else class="text-soft">—</span>
                                    <span
                                        v-if="lead.phone_type"
                                        class="mt-0.5 block text-[0.78rem] text-soft"
                                    >
                                        {{ lead.phone_type.replace('_', ' ') }}
                                        <template v-if="lead.phone_country">
                                            · {{ lead.phone_country }}
                                        </template>
                                    </span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[0.78rem] text-muted">
                                    Company
                                </dt>
                                <dd
                                    class="mt-0.5 flex items-center gap-1.5 text-[0.92rem] text-strong"
                                >
                                    <Building2
                                        v-if="lead.company_name"
                                        class="size-3.5 shrink-0 text-soft"
                                    />
                                    {{ lead.company_name ?? '—' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[0.78rem] text-muted">Role</dt>
                                <dd class="mt-0.5 text-[0.92rem] text-strong">
                                    {{ lead.job_title ?? '—' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[0.78rem] text-muted">
                                    Website
                                </dt>
                                <dd class="mt-0.5">
                                    <a
                                        v-if="lead.website"
                                        :href="lead.website"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center gap-1.5 truncate text-[0.92rem] text-strong hover:text-primary-600"
                                    >
                                        <Globe
                                            class="size-3.5 shrink-0 text-soft"
                                        />
                                        {{ lead.website }}
                                    </a>
                                    <span v-else class="text-soft">—</span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[0.78rem] text-muted">
                                    Country
                                </dt>
                                <dd class="mt-0.5 text-[0.92rem] text-strong">
                                    {{ lead.country ?? '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Consent (§88) -->
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Permission to contact
                        </h2>
                        <p
                            class="mt-2 flex items-start gap-2 text-[0.9rem]"
                            :class="lead.consent ? 'text-body' : 'text-muted'"
                        >
                            <component
                                :is="lead.consent ? Check : ShieldAlert"
                                class="mt-0.5 size-4 shrink-0"
                                :class="
                                    lead.consent
                                        ? 'text-success'
                                        : 'text-warning'
                                "
                                aria-hidden="true"
                            />
                            <span>
                                <template v-if="lead.consent">
                                    Agreed on
                                    {{ formatDate(lead.consent_at, true) }}
                                    <template v-if="lead.consent_source">
                                        via {{ lead.consent_source }}
                                    </template>
                                </template>
                                <template v-else>
                                    No recorded agreement. Check your lawful
                                    basis before contacting this person.
                                </template>
                            </span>
                        </p>
                    </div>

                    <!-- Verification findings (§59) -->
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2
                                class="text-[0.95rem] font-semibold text-strong"
                            >
                                Details check
                            </h2>
                            <StatusBadge
                                :tone="verification.tone as never"
                                :label="verification.label"
                            />
                        </div>

                        <p
                            v-if="verification.verified_at"
                            class="mt-1 text-[0.82rem] text-muted"
                        >
                            Confidence {{ verification.confidence }} of 100 ·
                            checked
                            {{ formatDate(verification.verified_at, true) }}
                        </p>
                        <p v-else class="mt-1 text-[0.82rem] text-muted">
                            Not checked yet.
                        </p>

                        <ul v-if="problems.length" class="mt-3 space-y-1.5">
                            <li
                                v-for="finding in problems"
                                :key="finding.check"
                                class="flex items-start gap-2 text-[0.88rem]"
                            >
                                <component
                                    :is="
                                        finding.verdict === 'fail'
                                            ? ShieldAlert
                                            : TriangleAlert
                                    "
                                    class="mt-0.5 size-3.5 shrink-0"
                                    :class="
                                        finding.verdict === 'fail'
                                            ? 'text-danger'
                                            : 'text-warning'
                                    "
                                    aria-hidden="true"
                                />
                                <span class="text-body">{{
                                    finding.detail
                                }}</span>
                            </li>
                        </ul>

                        <p
                            v-else-if="verification.verified_at"
                            class="mt-3 flex items-center gap-2 text-[0.88rem] text-body"
                        >
                            <ShieldCheck class="size-4 text-success" />
                            Nothing wrong with these details.
                        </p>
                    </div>

                    <!-- Merged duplicates -->
                    <div
                        v-if="duplicates.length"
                        class="rounded-xl border border-border bg-surface p-5"
                    >
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Merged into this lead
                        </h2>
                        <p class="mt-1 text-[0.82rem] text-muted">
                            This record's history is combined from
                            {{ duplicates.length + 1 }} submissions.
                        </p>
                        <ul class="mt-3 space-y-1.5">
                            <li
                                v-for="duplicate in duplicates"
                                :key="duplicate.id"
                                class="flex flex-wrap items-center gap-2 text-[0.88rem]"
                            >
                                <span class="text-strong">
                                    {{ duplicate.name }}
                                </span>
                                <span v-if="duplicate.email" class="text-muted">
                                    {{ duplicate.email }}
                                </span>
                                <span class="text-soft tabular-nums">
                                    {{ formatDate(duplicate.created_at) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Timeline (§86) -->
                <div v-else-if="tab === 'timeline'" class="mt-4">
                    <div
                        v-if="timeline.length === 0"
                        class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
                    >
                        Nothing has happened to this lead yet.
                    </div>

                    <ol v-else class="space-y-0">
                        <li
                            v-for="(event, index) in timeline"
                            :key="event.id"
                            class="relative flex gap-3 pb-4 pl-1"
                        >
                            <!-- The thread, stopping at the last entry rather
                                 than dangling below it. -->
                            <span
                                v-if="index < timeline.length - 1"
                                class="absolute top-5 left-[0.6rem] h-full w-px bg-border"
                                aria-hidden="true"
                            />
                            <span
                                class="relative z-10 mt-1 size-2.5 shrink-0 rounded-full bg-primary-500 ring-3 ring-[var(--color-page)]"
                                aria-hidden="true"
                            />

                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    class="text-left"
                                    :aria-expanded="expandedEvent === event.id"
                                    @click="
                                        expandedEvent =
                                            expandedEvent === event.id
                                                ? null
                                                : event.id
                                    "
                                >
                                    <span
                                        class="block text-[0.9rem] font-medium text-strong"
                                    >
                                        {{ event.label }}
                                    </span>
                                    <span
                                        class="block text-[0.8rem] text-muted tabular-nums"
                                    >
                                        {{
                                            formatDate(event.occurred_at, true)
                                        }}
                                        <template v-if="event.user">
                                            · {{ event.user }}
                                        </template>
                                        <template v-else-if="event.provider">
                                            · {{ event.provider }}
                                        </template>
                                    </span>
                                </button>

                                <pre
                                    v-if="
                                        expandedEvent === event.id &&
                                        event.payload
                                    "
                                    class="mt-2 overflow-x-auto rounded-lg bg-surface-alt p-3 text-[0.75rem] text-muted"
                                    >{{
                                        JSON.stringify(event.payload, null, 2)
                                    }}</pre>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Deals -->
                <div v-else-if="tab === 'deals'" class="mt-4">
                    <div
                        v-if="deals.length === 0"
                        class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
                    >
                        No deals have been opened from this lead.
                    </div>

                    <ul
                        v-else
                        class="divide-y divide-border-soft overflow-hidden rounded-xl border border-border bg-surface"
                    >
                        <li
                            v-for="deal in deals"
                            :key="deal.id"
                            class="flex flex-wrap items-center gap-3 px-4 py-3"
                        >
                            <span
                                class="min-w-0 flex-1 truncate text-[0.92rem] font-medium text-strong"
                            >
                                {{ deal.title }}
                            </span>
                            <span class="text-[0.85rem] text-muted">
                                {{ deal.pipeline }} · {{ deal.stage }}
                            </span>
                            <span
                                class="text-[0.92rem] font-semibold text-strong tabular-nums"
                            >
                                {{ money(deal.value, deal.currency) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Source and attribution (§2, §34) -->
                <div v-else-if="tab === 'source'" class="mt-4 space-y-4">
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Where this lead came from
                        </h2>

                        <p
                            v-if="attribution.source"
                            class="mt-2 flex flex-wrap items-center gap-2 text-[0.92rem]"
                        >
                            <span class="font-medium text-strong">
                                {{ attribution.source.name }}
                            </span>
                            <span class="text-muted">
                                {{ attribution.source.type }}
                            </span>
                            <!-- §2. An imported list and a verified provider
                                 submission must not look the same. -->
                            <span
                                v-if="attribution.source.authorized_api"
                                class="dark:bg-primary-950 inline-flex items-center gap-1 rounded bg-primary-50 px-1.5 py-0.5 text-[0.75rem] font-medium text-primary-700 dark:text-primary-300"
                            >
                                <ShieldCheck class="size-3" />
                                Authorized provider
                            </span>
                        </p>
                        <p v-else class="mt-2 text-[0.9rem] text-muted">
                            No source was recorded.
                        </p>

                        <dl
                            v-if="utmEntries.length"
                            class="mt-4 grid gap-2 border-t border-border pt-4 sm:grid-cols-2"
                        >
                            <div v-for="[key, value] in utmEntries" :key="key">
                                <dt class="font-mono text-[0.75rem] text-muted">
                                    {{ key }}
                                </dt>
                                <dd
                                    class="text-[0.88rem] break-all text-strong"
                                >
                                    {{ value }}
                                </dd>
                            </div>
                        </dl>

                        <dl class="mt-4 space-y-2 border-t border-border pt-4">
                            <div v-if="attribution.landing_page">
                                <dt class="text-[0.78rem] text-muted">
                                    Landing page
                                </dt>
                                <dd
                                    class="text-[0.88rem] break-all text-strong"
                                >
                                    {{ attribution.landing_page }}
                                </dd>
                            </div>
                            <div v-if="attribution.referrer">
                                <dt class="text-[0.78rem] text-muted">
                                    Referrer
                                </dt>
                                <dd
                                    class="text-[0.88rem] break-all text-strong"
                                >
                                    {{ attribution.referrer }}
                                </dd>
                            </div>
                            <div v-if="attribution.external_system">
                                <dt class="text-[0.78rem] text-muted">
                                    External record
                                </dt>
                                <dd class="text-[0.88rem] text-strong">
                                    {{ attribution.external_system }} ·
                                    {{ attribution.external_record_id }}
                                </dd>
                            </div>
                        </dl>
                    </div>
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
                        No recorded changes to this lead.
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
                <div class="rounded-xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[0.78rem] text-muted">Score</span>
                        <ScoreBadge :score="score.score" />
                    </div>

                    <!-- §19. A rep who cannot see why a lead scored 82 has no
                         way to trust it, and will ignore the number. -->
                    <ul v-if="score.reasons.length" class="mt-3 space-y-1">
                        <li
                            v-for="reason in score.reasons"
                            :key="reason.label"
                            class="flex items-baseline justify-between gap-2 text-[0.85rem]"
                        >
                            <span class="text-body">{{ reason.label }}</span>
                            <span class="shrink-0 text-muted tabular-nums">
                                +{{ reason.points }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-[0.82rem] text-muted">
                        No scoring rules matched this lead.
                    </p>
                </div>

                <div
                    class="space-y-3 rounded-xl border border-border bg-surface p-5"
                >
                    <div>
                        <p class="text-[0.78rem] text-muted">Status</p>
                        <div class="mt-1">
                            <StatusBadge
                                :tone="lead.status_tone as never"
                                :label="lead.status_label"
                            />
                        </div>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Assigned to</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ lead.owner ?? 'Nobody yet' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Next follow-up</p>
                        <p
                            class="mt-0.5 flex items-center gap-1.5 text-[0.9rem] text-strong"
                        >
                            <Calendar
                                v-if="lead.next_follow_up_at"
                                class="size-3.5 shrink-0 text-soft"
                            />
                            {{ formatDate(lead.next_follow_up_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Last activity</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ formatDate(lead.last_activity_at, true) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[0.78rem] text-muted">Created</p>
                        <p class="mt-0.5 text-[0.9rem] text-strong">
                            {{ formatDate(lead.created_at, true) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="lead.tags.length"
                    class="rounded-xl border border-border bg-surface p-5"
                >
                    <p class="text-[0.78rem] text-muted">Tags</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-for="tag in lead.tags"
                            :key="tag.id"
                            class="rounded bg-surface-alt px-2 py-0.5 text-[0.8rem] text-body"
                        >
                            {{ tag.name }}
                        </span>
                    </div>
                </div>
            </aside>
        </div>

        <LeadFormDrawer
            :open="editing"
            :lead="editable"
            :options="options"
            @close="editing = false"
        />
    </AppLayout>
</template>
