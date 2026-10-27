<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    Search,
    Sparkles,
    Target,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
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
    counts: Record<string, number>;
    hasProfiles: boolean;
    highFit: number;
    pageSizes: number[];
}>();

const { can } = useAuthorization();
const toasts = useToastStore();

const search = ref(props.filters.search);
const open = ref<string | null>(null);
const drafts = ref<Record<string, string>>({});
const busy = ref<string | null>(null);

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
            'Copied. Paste it on ' +
                (item.type === 'tender' ? 'your letter' : item.source) +
                '.',
        );
    }
}

function convert(item: Opportunity): void {
    router.post(`/opportunities/${item.id}/convert`);
}

function fitTone(fit: number): Tone {
    return fit >= 75 ? 'success' : fit >= props.highFit ? 'info' : 'neutral';
}

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    const date =
        value.length === 10 ? new Date(`${value}T00:00:00`) : new Date(value);

    return date.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
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
                Pakistan government tenders, Upwork jobs and remote job-board
                posts, scored against your services. Draft a proposal, review
                it, then submit it yourself on Upwork, EPADS or by email:
                nothing is sent automatically. Job posts are shown with a link
                back to the board that published them.
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
                        aria-hidden="true"
                    />
                    <TextInput
                        id="opp-search"
                        v-model="search"
                        class="pl-9"
                        placeholder="Search title or company…"
                        aria-label="Search opportunities"
                    />
                </div>
                <div class="w-44">
                    <SelectMenu
                        id="opp-source"
                        :model-value="filters.source"
                        :options="[
                            { value: '', label: 'All sources' },
                            ...sources,
                        ]"
                        @update:model-value="(v) => navigate({ source: v })"
                    />
                </div>
                <div class="w-44">
                    <SelectMenu
                        id="opp-fit"
                        :model-value="String(filters.min_fit)"
                        :options="fitOptions"
                        @update:model-value="(v) => navigate({ min_fit: v })"
                    />
                </div>
                <div class="w-40">
                    <SelectMenu
                        id="opp-status"
                        :model-value="filters.status"
                        :options="statusOptions"
                        @update:model-value="(v) => navigate({ status: v })"
                    />
                </div>
                <div v-if="profiles.length > 1" class="w-52">
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
                    {{ counts.tender ?? 0 }} strong tenders ·
                    {{ counts.job_post ?? 0 }} strong jobs to act on
                </p>
            </div>

            <div
                v-if="matches.data.length === 0"
                class="rounded-xl border border-border bg-surface"
            >
                <EmptyState
                    title="Nothing matches these filters"
                    description="Lower the fit filter, include other statuses, or wait for the next fetch: tenders and job boards update twice a day, Upwork every two hours."
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
                                    >· {{ formatDate(item.posted_at) }}</span
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
                                Open on {{ item.source }}
                            </a>
                            <Button
                                v-if="can('lead.update')"
                                variant="primary"
                                size="sm"
                                :loading="busy === item.id"
                                @click="draft(item)"
                            >
                                <Sparkles class="size-4" />
                                {{
                                    item.draft
                                        ? 'Redraft'
                                        : item.type === 'tender'
                                          ? 'Draft letter'
                                          : 'Draft proposal'
                                }}
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
