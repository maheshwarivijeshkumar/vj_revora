<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    RefreshCw,
    Search,
    Sparkles,
    Target,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { useClock } from '@/composables/useClock';
import AppLayout from '@/layouts/AppLayout.vue';
import { copyText } from '@/lib/clipboard';
import { useToastStore } from '@/stores/toast';

type Opportunity = {
    id: string;
    type: 'tender' | 'job_post';
    fit: number;
    services: string[];
    reasons: string[];
    status: string;
    status_label: string;
    status_tone: Tone;
    profile: string | null;
    draft: string | null;
    drafted_by: string | null;
    notes: string | null;
    lead_id: number | null;
    source: string;
    title: string;
    who: string | null;
    where: string | null;
    reference: string | null;
    budget: string | null;
    posted_at: string | null;
    closing_at: string | null;
    url: string | null;
    summary: string | null;
};

type Option = { value: string; label: string };

const props = defineProps<{
    matches: {
        data: Opportunity[];
        from: number | null;
        to: number | null;
        total: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    filters: {
        search: string;
        source: string;
        status: string;
        min_fit: number;
        profile: string;
        per_page: number;
    };
    sources: Option[];
    statuses: Option[];
    profiles: Option[];
    resumes: { id: string; name: string; kind: string; about: string }[];
    canEditResume: boolean;
    counts: Record<string, number>;
    hasProfiles: boolean;
    highFit: number;
    pageSizes: number[];
}>();

const { can } = useAuthorization();
const toasts = useToastStore();
const { formatDate } = useClock();

const search = ref(props.filters.search);
const open = ref<string | null>(null);
const drafts = ref<Record<string, string>>({});
const busy = ref<string | null>(null);
const syncing = ref(false);
const syncSources = ref(props.sources.map((source) => source.value));
const resumeId = ref(props.resumes[0]?.id ?? '');
const resumeText = ref(props.resumes[0]?.about ?? '');
const savingResume = ref(false);

function navigate(params: Record<string, unknown>, resetPage = true): void {
    router.get(
        '/opportunities',
        {
            search: props.filters.search,
            source: props.filters.source,
            status: props.filters.status,
            min_fit: props.filters.min_fit,
            profile: props.filters.profile,
            per_page: props.filters.per_page,
            ...(resetPage ? { page: 1 } : {}),
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => navigate({ search: value }), 300);
});

const fitOptions = [
    { value: '60', label: 'Strong fit (60+)' },
    { value: '45', label: 'Good fit (45+)' },
    { value: '35', label: 'Everything matched' },
];

const statusOptions = [
    { value: 'open', label: 'To act on' },
    ...props.statuses,
    { value: 'all', label: 'All' },
];

function toggle(item: Opportunity): void {
    open.value = open.value === item.id ? null : item.id;
    drafts.value[item.id] ??= item.draft ?? '';
}

function draft(item: Opportunity): void {
    busy.value = item.id;
    router.post(
        `/opportunities/${item.id}/draft`,
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const fresh = (
                    page.props.matches as typeof props.matches
                ).data.find((m) => m.id === item.id);
                drafts.value[item.id] = fresh?.draft ?? '';
                open.value = item.id;
            },
            onFinish: () => (busy.value = null),
        },
    );
}

function setStatus(item: Opportunity, status: string): void {
    router.patch(
        `/opportunities/${item.id}`,
        { status },
        { preserveScroll: true },
    );
}

function saveDraft(item: Opportunity): void {
    router.patch(
        `/opportunities/${item.id}`,
        { draft: drafts.value[item.id] ?? '' },
        { preserveScroll: true },
    );
}

async function copyDraft(item: Opportunity): Promise<void> {
    if (await copyText(drafts.value[item.id] ?? '')) {
        toasts.success(
            'Copied. Paste it on ' + item.source + ' and apply there yourself.',
        );
    }
}

function convert(item: Opportunity): void {
    router.post(`/opportunities/${item.id}/convert`);
}

function fitTone(fit: number): Tone {
    return fit >= 75 ? 'success' : fit >= props.highFit ? 'info' : 'neutral';
}

function postedOn(value: string | null): string {
    if (!value) {
        return '';
    }

    if (value.length === 10) {
        const [year, month, day] = value.split('-').map(Number);

        return new Date(year, month - 1, day).toLocaleDateString(undefined, {
            day: 'numeric',
            month: 'short',
        });
    }

    return formatDate(value, { day: 'numeric', month: 'short' });
}

function toggleSyncSource(source: string, on: boolean): void {
    syncSources.value = on
        ? [...syncSources.value, source]
        : syncSources.value.filter((value) => value !== source);
}

function syncNow(): void {
    syncing.value = true;

    router.post(
        '/opportunities/sync',
        { sources: syncSources.value },
        {
            preserveScroll: true,
            onFinish: () => (syncing.value = false),
        },
    );
}

function chooseResume(id: string): void {
    resumeId.value = id;
    resumeText.value =
        props.resumes.find((resume) => resume.id === id)?.about ?? '';
}

function saveResume(): void {
    savingResume.value = true;

    router.patch(
        '/opportunities/resume',
        { profile: resumeId.value, about: resumeText.value },
        {
            preserveScroll: true,
            onFinish: () => (savingResume.value = false),
        },
    );
}

function daysLeft(value: string | null): string {
    if (!value) {
        return '';
    }

    const days = Math.ceil(
        (new Date(value).getTime() - Date.now()) / 86_400_000,
    );

    return days <= 0 ? 'closes today' : `${days}d left`;
}
</script>

<template>
    <Head title="Opportunities" />

    <AppLayout
        title="Opportunities"
        :breadcrumbs="[{ label: 'Opportunities' }]"
    >
        <div class="space-y-3">
            <div
                v-if="!hasProfiles"
                class="rounded-xl border border-dashed border-border bg-surface px-5 py-6 text-[0.9rem]"
            >
                <p class="font-medium text-strong">
                    Tell the CRM what you sell first
                </p>
                <p class="mt-1 text-muted">
                    Opportunities are scored against your service profiles.
                    <Link
                        href="/settings/services"
                        class="font-medium text-primary-600 hover:underline"
                    >
                        Set up Berg Media Digital and your freelancer profile
                    </Link>
                    and matching starts straight away.
                </p>
            </div>

            <p class="max-w-4xl text-[0.88rem] text-muted">
                Upwork, Remotive, Remote OK and Arbeitnow, scored against
                your services. Draft a proposal from your resume, edit it,
                then apply yourself on the site that posted the job. Nothing
                is submitted for you.
            </p>

            <div
                v-if="resumes.length"
                class="rounded-xl border border-border bg-surface p-4"
            >
                <div class="grid gap-4 lg:grid-cols-[16rem_1fr]">
                    <div>
                        <h2 class="text-[0.95rem] font-semibold text-strong">
                            Resume
                        </h2>
                        <p class="mt-1 text-[0.82rem] text-muted">
                            Proposals are written only from this text. Change
                            it here before you draft.
                        </p>
                        <div v-if="resumes.length > 1" class="mt-3">
                            <SelectMenu
                                id="opp-resume"
                                :model-value="resumeId"
                                :options="
                                    resumes.map((resume) => ({
                                        value: resume.id,
                                        label: resume.name,
                                    }))
                                "
                                @update:model-value="chooseResume"
                            />
                        </div>
                    </div>
                    <div>
                        <FormField
                            id="opp-resume-text"
                            label="About, experience and resume"
                            hint="Paste the full resume. The next draft uses the saved text."
                        >
                            <TextArea
                                id="opp-resume-text"
                                v-model="resumeText"
                                :rows="6"
                                :disabled="!canEditResume"
                            />
                        </FormField>
                        <div class="mt-2 flex justify-end">
                            <Button
                                v-if="canEditResume"
                                type="button"
                                variant="secondary"
                                size="sm"
                                :loading="savingResume"
                                @click="saveResume"
                            >
                                Save resume
                            </Button>
                            <Link
                                v-else
                                href="/settings/services"
                                class="text-[0.85rem] font-medium text-primary-600 hover:underline"
                            >
                                Edit profiles
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <FormField id="opp-search" label="Search">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted"
                                aria-hidden="true"
                            />
                            <TextInput
                                id="opp-search"
                                v-model="search"
                                class="!pl-9"
                                placeholder="Title or company…"
                            />
                        </div>
                    </FormField>
                    <FormField id="opp-source" label="Source">
                        <SelectMenu
                            id="opp-source"
                            :model-value="filters.source"
                            :options="[
                                { value: '', label: 'All sources' },
                                ...sources,
                            ]"
                            @update:model-value="(v) => navigate({ source: v })"
                        />
                    </FormField>
                    <FormField id="opp-fit" label="Fit">
                        <SelectMenu
                            id="opp-fit"
                            :model-value="String(filters.min_fit)"
                            :options="fitOptions"
                            @update:model-value="(v) => navigate({ min_fit: v })"
                        />
                    </FormField>
                    <FormField id="opp-status" label="Status">
                        <SelectMenu
                            id="opp-status"
                            :model-value="filters.status"
                            :options="statusOptions"
                            @update:model-value="(v) => navigate({ status: v })"
                        />
                    </FormField>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
                >
                    <div v-if="profiles.length > 1" class="w-56">
                        <SelectMenu
                            id="opp-profile"
                            :model-value="filters.profile"
                            :options="[
                                { value: '', label: 'Both profiles' },
                                ...profiles,
                            ]"
                            @update:model-value="(v) => navigate({ profile: v })"
                        />
                    </div>
                    <p class="ml-auto text-[0.82rem] text-muted">
                        {{ counts.job_post ?? 0 }} strong jobs to act on
                    </p>
                </div>
                <div
                    v-if="can('lead.update')"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
                >
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        <label
                            v-for="source in sources"
                            :key="source.value"
                            class="flex items-center gap-2 text-[0.85rem] text-body"
                        >
                            <Checkbox
                                :id="`sync-${source.value}`"
                                :model-value="syncSources.includes(source.value)"
                                @update:model-value="
                                    toggleSyncSource(source.value, $event)
                                "
                            />
                            {{ source.label }}
                        </label>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        :loading="syncing"
                        :disabled="syncSources.length === 0"
                        @click="syncNow"
                    >
                        <RefreshCw class="size-4" />
                        Sync now
                    </Button>
                </div>
            </div>

            <div
                v-if="matches.data.length === 0"
                class="rounded-xl border border-border bg-surface"
            >
                <EmptyState
                    title="Nothing matches these filters"
                    description="Lower the fit filter, include other statuses, or sync a source. You apply yourself on the site that posted the job."
                >
                    <template #icon><Target class="size-5" /></template>
                </EmptyState>
            </div>

            <ul v-else class="space-y-2">
                <li
                    v-for="item in matches.data"
                    :key="item.id"
                    class="rounded-xl border border-border bg-surface"
                >
                    <button
                        type="button"
                        class="flex w-full items-start gap-4 px-4 py-3 text-left"
                        @click="toggle(item)"
                    >
                        <div class="flex w-14 shrink-0 flex-col items-center">
                            <span
                                class="text-[1.25rem] font-semibold text-strong tabular-nums"
                                >{{ item.fit }}</span
                            >
                            <StatusBadge
                                :tone="fitTone(item.fit)"
                                label="fit"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center gap-1.5 text-[0.75rem] text-muted"
                            >
                                <span class="font-medium text-body">{{
                                    item.source
                                }}</span>
                                <span v-if="item.who">· {{ item.who }}</span>
                                <span v-if="item.where"
                                    >· {{ item.where }}</span
                                >
                                <span v-if="item.posted_at"
                                    >· {{ postedOn(item.posted_at) }}</span
                                >
                                <span
                                    v-if="item.closing_at"
                                    class="font-medium text-danger"
                                    >· {{ daysLeft(item.closing_at) }}</span
                                >
                                <span
                                    v-if="item.budget"
                                    class="font-medium text-strong"
                                    >· {{ item.budget }}</span
                                >
                            </div>
                            <p class="mt-0.5 font-medium text-strong">
                                {{ item.title }}
                            </p>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-1.5"
                            >
                                <StatusBadge
                                    :tone="item.status_tone"
                                    :label="item.status_label"
                                />
                                <span
                                    v-for="service in item.services.slice(0, 3)"
                                    :key="service"
                                    class="rounded-full bg-surface-alt px-2 py-0.5 text-[0.72rem] text-body"
                                    >{{ service }}</span
                                >
                                <span
                                    v-if="item.profile"
                                    class="text-[0.72rem] text-muted"
                                    >as {{ item.profile }}</span
                                >
                            </div>
                        </div>
                    </button>

                    <div
                        v-if="open === item.id"
                        class="space-y-3 border-t border-border px-4 py-4"
                    >
                        <p v-if="item.summary" class="text-[0.85rem] text-body">
                            {{ item.summary }}
                        </p>
                        <ul
                            v-if="item.reasons.length"
                            class="flex flex-wrap gap-x-4 gap-y-1 text-[0.8rem] text-muted"
                        >
                            <li v-for="reason in item.reasons" :key="reason">
                                • {{ reason }}
                            </li>
                        </ul>

                        <div class="flex flex-wrap gap-2">
                            <a
                                v-if="item.url"
                                :href="item.url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-surface-alt px-3 text-[0.85rem] font-medium text-strong hover:bg-surface-sunken"
                            >
                                <ExternalLink class="size-4" />
                                Apply on {{ item.source }}
                            </a>
                            <Button
                                v-if="can('lead.update')"
                                variant="primary"
                                size="sm"
                                :loading="busy === item.id"
                                @click="draft(item)"
                            >
                                <Sparkles class="size-4" />
                                {{ item.draft ? 'Redraft' : 'Draft proposal' }}
                            </Button>
                            <Button
                                v-if="
                                    can('lead.update') &&
                                    item.status !== 'applied'
                                "
                                variant="secondary"
                                size="sm"
                                @click="setStatus(item, 'applied')"
                            >
                                <Check class="size-4" />
                                Mark applied
                            </Button>
                            <Button
                                v-if="can('lead.create') && !item.lead_id"
                                variant="secondary"
                                size="sm"
                                @click="convert(item)"
                            >
                                <UserPlus class="size-4" />
                                Add to leads
                            </Button>
                            <Link
                                v-else-if="item.lead_id"
                                :href="`/leads/${item.lead_id}`"
                                class="inline-flex h-8 items-center px-2 text-[0.85rem] font-medium text-primary-600 hover:underline"
                                >View lead</Link
                            >
                            <Button
                                v-if="
                                    can('lead.update') &&
                                    item.status !== 'dismissed'
                                "
                                variant="ghost"
                                size="sm"
                                @click="setStatus(item, 'dismissed')"
                            >
                                <X class="size-4" />
                                Not a fit
                            </Button>
                            <template
                                v-if="
                                    ['applied', 'won', 'lost'].includes(
                                        item.status,
                                    ) && can('lead.update')
                                "
                            >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="setStatus(item, 'won')"
                                    >Won</Button
                                >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="setStatus(item, 'lost')"
                                    >Lost</Button
                                >
                            </template>
                        </div>

                        <div v-if="drafts[item.id]" class="space-y-2">
                            <p class="text-[0.78rem] text-muted">
                                Draft
                                {{
                                    item.drafted_by === 'template'
                                        ? 'from a template'
                                        : `by ${item.drafted_by}`
                                }}. Check every fact before sending.
                            </p>
                            <TextArea
                                :id="`draft-${item.id}`"
                                v-model="drafts[item.id]"
                                :rows="12"
                            />
                            <div class="flex gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    @click="copyDraft(item)"
                                >
                                    <Copy class="size-4" />
                                    Copy
                                </Button>
                                <Button
                                    v-if="can('lead.update')"
                                    variant="ghost"
                                    size="sm"
                                    @click="saveDraft(item)"
                                    >Save edits</Button
                                >
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <DataTablePagination
                v-if="matches.total > 0"
                :from="matches.from"
                :to="matches.to"
                :total="matches.total"
                :current-page="matches.current_page"
                :last-page="matches.last_page"
                :per-page="matches.per_page"
                :page-sizes="pageSizes"
                @page="(page) => navigate({ page }, false)"
                @per-page="(size) => navigate({ per_page: size })"
            />
        </div>
    </AppLayout>
</template>
