<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    Download,
    Eye,
    Pencil,
    Plus,
    ShieldCheck,
    Tag,
    Trash2,
    UserRoundCog,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import BulkActionBar from '@/components/data/BulkActionBar.vue';
import DataTable, {
    type Column,
    type Density,
} from '@/components/data/DataTable.vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import DataTableToolbar from '@/components/data/DataTableToolbar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ScoreBadge from '@/components/ui/ScoreBadge.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { useToastStore } from '@/stores/toast';

type LeadRow = {
    id: number;
    uuid: string;
    name: string;
    email: string | null;
    phone: string | null;
    company: string | null;
    status: string;
    status_label: string;
    status_tone: string;
    score: number;
    owner: string | null;
    source: string | null;
    source_authorized: boolean;
    last_activity_at: string | null;
    next_follow_up_at: string | null;
    created_at: string | null;
};

type Paginated = {
    data: LeadRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = defineProps<{
    leads: Paginated;
    filters: {
        search: string;
        status: string[];
        owner_id: string[];
        source_id: string[];
        score_min: number | null;
        score_max: number | null;
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    };
    options?: {
        statuses: { value: string; label: string; tone: string }[];
        owners: SelectOption[];
        sources: SelectOption[];
    };
    pageSizes: number[];
}>();

const page = usePage();
const toasts = useToastStore();
const { can } = useAuthorization();

/** Which bulk form is open, if any. */
const bulkAction = ref<'assign' | 'change_status' | 'add_tag' | null>(null);
const bulkOwner = ref('');
const bulkStatus = ref('');
const bulkTag = ref('');
const bulkProgress = ref<{
    label: string;
    processed: number;
    total: number;
    percent: number;
} | null>(null);

let poll: ReturnType<typeof setInterval> | undefined;

onUnmounted(() => {
    if (poll) {
        clearInterval(poll);
    }
});

const selected = ref<(number | string)[]>([]);
const density = ref<Density>('default');
const filtersOpen = ref(false);
const loading = ref(false);

// Selection is per page, so it must not survive a page change: acting on
// rows the user can no longer see is how bulk actions go wrong (§113).
watch(
    () => props.leads.current_page,
    () => (selected.value = []),
);

const columns: Column<LeadRow>[] = [
    { key: 'name', label: 'Lead', sortable: true },
    { key: 'company', label: 'Company', sortable: true },
    { key: 'source', label: 'Source' },
    { key: 'status', label: 'Status', sortable: true },
    {
        key: 'score',
        label: 'Score',
        sortable: true,
        numeric: true,
        width: '7rem',
    },
    { key: 'owner', label: 'Assigned to', secondary: true },
    {
        key: 'last_activity_at',
        label: 'Last activity',
        sortable: true,
        secondary: true,
    },
    {
        key: 'next_follow_up_at',
        label: 'Next follow-up',
        sortable: true,
        secondary: true,
    },
    { key: 'created_at', label: 'Created', sortable: true, secondary: true },
];

const visible = ref<string[]>([
    'name',
    'company',
    'source',
    'status',
    'score',
    'owner',
    'last_activity_at',
]);

const activeFilterCount = computed(
    () =>
        props.filters.status.length +
        props.filters.owner_id.length +
        props.filters.source_id.length +
        (props.filters.score_min !== null ? 1 : 0) +
        (props.filters.score_max !== null ? 1 : 0),
);

/**
 * Pushes table state into the URL.
 *
 * Keeping it in the query string means a filtered view is linkable and
 * survives the back button (§111), and it is what makes the whole table
 * server-driven rather than holding a second copy of the state in the client.
 */
function navigate(params: Record<string, unknown>, resetPage = true): void {
    loading.value = true;

    router.get(
        '/leads',
        {
            search: props.filters.search,
            status: props.filters.status,
            owner_id: props.filters.owner_id,
            source_id: props.filters.source_id,
            score_min: props.filters.score_min,
            score_max: props.filters.score_max,
            sort: props.filters.sort,
            direction: props.filters.direction,
            per_page: props.filters.per_page,
            ...(resetPage ? { page: 1 } : {}),
            ...params,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => (loading.value = false),
        },
    );
}

function onSort(key: string): void {
    const direction =
        props.filters.sort === key && props.filters.direction === 'desc'
            ? 'asc'
            : 'desc';

    navigate({ sort: key, direction });
}

function clearFilters(): void {
    navigate({
        status: [],
        owner_id: [],
        source_id: [],
        score_min: null,
        score_max: null,
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Submits a bulk action for the current selection.
 *
 * The server decides whether to run it now or queue it; a selection small
 * enough to finish inside the request gets its answer immediately rather than a
 * progress bar for something that took 80ms (§113).
 */
function submitBulk(action: string, extra: Record<string, unknown> = {}): void {
    router.post(
        '/leads/bulk',
        { action, ids: selected.value, ...extra },
        {
            preserveScroll: true,
            onSuccess: () => {
                bulkAction.value = null;
                selected.value = [];
                startPollingIfQueued();
            },
        },
    );
}

function applyAssign(): void {
    // An empty value is meaningful: it unassigns.
    submitBulk('assign', { owner_id: bulkOwner.value || null });
}

function applyStatus(): void {
    if (!bulkStatus.value) {
        return;
    }

    submitBulk('change_status', { status: bulkStatus.value });
}

function applyTag(): void {
    const tag = bulkTag.value.trim();

    if (!tag) {
        return;
    }

    submitBulk('add_tag', { tag });
}

function confirmDelete(): void {
    // The exact count, because a destructive action must say what it will
    // affect (§113).
    if (
        !window.confirm(
            `Delete ${selected.value.length} ${
                selected.value.length === 1 ? 'lead' : 'leads'
            }? They can be restored by an administrator.`,
        )
    ) {
        return;
    }

    submitBulk('delete');
}

/**
 * Polls a queued operation so a long run shows real progress rather than a
 * spinner that means nothing (§113).
 */
function startPollingIfQueued(): void {
    const flash = page.props.flash as Record<string, unknown> | undefined;
    const queued = flash?.bulkOperation as
        | { id: string; total: number; action: string }
        | undefined;

    if (!queued) {
        return;
    }

    bulkProgress.value = {
        label: queued.action,
        processed: 0,
        total: queued.total,
        percent: 0,
    };

    poll = setInterval(() => void checkProgress(queued.id), 1500);
}

async function checkProgress(id: string): Promise<void> {
    try {
        const response = await fetch(`/bulk-operations/${id}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const state = (await response.json()) as {
            processed: number;
            total: number;
            progress: number;
            finished: boolean;
            failed: number;
            summary: string | null;
        };

        if (bulkProgress.value) {
            bulkProgress.value.processed = state.processed;
            bulkProgress.value.percent = state.progress;
        }

        if (!state.finished) {
            return;
        }

        clearInterval(poll);
        bulkProgress.value = null;

        if (state.failed > 0) {
            toasts.warning(state.summary ?? 'Finished with skipped records.');
        } else {
            toasts.success(state.summary ?? 'Done.');
        }

        // The table still shows the rows as they were before the run.
        router.reload({ only: ['leads'] });
    } catch {
        clearInterval(poll);
        bulkProgress.value = null;
        toasts.error('Lost track of that bulk action', {
            description: 'Reload the page to see where it got to.',
        });
    }
}
</script>

<template>
    <Head title="Leads" />

    <AppLayout title="Leads" :breadcrumbs="[{ label: 'Leads' }]">
        <template #actions>
            <Button v-if="can('lead.export')" variant="secondary" size="sm">
                <Download class="size-4" />
                Export
            </Button>
            <Button v-if="can('lead.create')" variant="primary" size="sm">
                <Plus class="size-4" />
                New lead
            </Button>
        </template>

        <div class="space-y-3">
            <DataTableToolbar
                :search="filters.search"
                placeholder="Search name, email, phone, company…"
                :columns="columns"
                :visible="visible"
                :density="density"
                :active-filter-count="activeFilterCount"
                :can-export="can('lead.export')"
                @update:search="(value) => navigate({ search: value })"
                @update:visible="(keys) => (visible = keys)"
                @update:density="(value) => (density = value)"
                @toggle-filters="filtersOpen = !filtersOpen"
                @export="
                    toasts.info('Export arrives with the import/export module')
                "
            />

            <!-- Filter panel. Its options are deferred, so it renders a
                 placeholder until they arrive rather than blocking the table. -->
            <div
                v-if="filtersOpen"
                class="grid gap-3 rounded-xl border border-border bg-surface-alt p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <template v-if="options">
                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Status
                        </span>
                        <SelectMenu
                            id="filter-status"
                            :model-value="filters.status[0] ?? ''"
                            :options="
                                options.statuses.map((s) => ({
                                    value: s.value,
                                    label: s.label,
                                }))
                            "
                            placeholder="Any status"
                            clearable
                            @update:model-value="
                                (v) => navigate({ status: v ? [v] : [] })
                            "
                        />
                    </label>

                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Assigned to
                        </span>
                        <SelectMenu
                            id="filter-owner"
                            :model-value="filters.owner_id[0] ?? ''"
                            :options="options.owners"
                            placeholder="Anyone"
                            clearable
                            @update:model-value="
                                (v) => navigate({ owner_id: v ? [v] : [] })
                            "
                        />
                    </label>

                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Source
                        </span>
                        <SelectMenu
                            id="filter-source"
                            :model-value="filters.source_id[0] ?? ''"
                            :options="options.sources"
                            placeholder="Any source"
                            clearable
                            @update:model-value="
                                (v) => navigate({ source_id: v ? [v] : [] })
                            "
                        />
                    </label>

                    <div class="flex items-end">
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="activeFilterCount === 0"
                            @click="clearFilters"
                        >
                            Clear all
                        </Button>
                    </div>
                </template>

                <p v-else class="text-[0.9rem] text-muted">Loading filters…</p>
            </div>

            <BulkActionBar :count="selected.length" @clear="selected = []">
                <template #default="{ count }">
                    <Button
                        v-if="can('lead.assign')"
                        variant="ghost"
                        size="sm"
                        @click="
                            bulkAction =
                                bulkAction === 'assign' ? null : 'assign'
                        "
                    >
                        <UserRoundCog class="size-4" />
                        Assign
                    </Button>
                    <Button
                        v-if="can('lead.update')"
                        variant="ghost"
                        size="sm"
                        @click="
                            bulkAction =
                                bulkAction === 'change_status'
                                    ? null
                                    : 'change_status'
                        "
                    >
                        <ArrowRightLeft class="size-4" />
                        Status
                    </Button>
                    <Button
                        v-if="can('lead.update')"
                        variant="ghost"
                        size="sm"
                        @click="
                            bulkAction =
                                bulkAction === 'add_tag' ? null : 'add_tag'
                        "
                    >
                        <Tag class="size-4" />
                        Add tag
                    </Button>
                    <!-- The exact count, because a destructive action must
                         say what it will affect (§113). -->
                    <Button
                        v-if="can('lead.delete')"
                        variant="ghost"
                        size="sm"
                        class="text-danger"
                        @click="confirmDelete"
                    >
                        <Trash2 class="size-4" />
                        Delete {{ count }}
                    </Button>
                </template>
            </BulkActionBar>

            <!-- The chosen action's own argument, asked for only once it is
                 needed rather than as a permanently visible row of controls. -->
            <div
                v-if="bulkAction && selected.length"
                class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4"
            >
                <label v-if="bulkAction === 'assign'" class="block min-w-56">
                    <span
                        class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                    >
                        Assign {{ selected.length }} to
                    </span>
                    <SelectMenu
                        id="bulk-owner"
                        v-model="bulkOwner"
                        :options="[
                            { value: '', label: 'Nobody (unassign)' },
                            ...(options?.owners ?? []),
                        ]"
                    />
                </label>

                <label
                    v-else-if="bulkAction === 'change_status'"
                    class="block min-w-56"
                >
                    <span
                        class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                    >
                        Set {{ selected.length }} to
                    </span>
                    <SelectMenu
                        id="bulk-status"
                        v-model="bulkStatus"
                        :options="
                            (options?.statuses ?? []).map((s) => ({
                                value: s.value,
                                label: s.label,
                            }))
                        "
                    />
                </label>

                <label v-else class="block min-w-56">
                    <span
                        class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                    >
                        Tag {{ selected.length }} with
                    </span>
                    <TextInput
                        id="bulk-tag"
                        v-model="bulkTag"
                        placeholder="Trade show 2027"
                        @keydown.enter.prevent="applyTag"
                    />
                </label>

                <Button
                    variant="brand"
                    size="sm"
                    :disabled="
                        (bulkAction === 'change_status' && !bulkStatus) ||
                        (bulkAction === 'add_tag' && !bulkTag.trim())
                    "
                    @click="
                        bulkAction === 'assign'
                            ? applyAssign()
                            : bulkAction === 'change_status'
                              ? applyStatus()
                              : applyTag()
                    "
                >
                    Apply
                </Button>
                <Button variant="ghost" size="sm" @click="bulkAction = null">
                    Cancel
                </Button>
            </div>

            <!-- Real progress for a queued run, not a spinner (§113). -->
            <div
                v-if="bulkProgress"
                class="rounded-xl border border-border bg-surface p-4"
            >
                <div class="flex items-center justify-between">
                    <p class="text-[0.88rem] font-medium text-strong">
                        {{ bulkProgress.label }} in progress
                    </p>
                    <p class="text-[0.85rem] text-muted tabular-nums">
                        {{ bulkProgress.processed }} of
                        {{ bulkProgress.total }}
                    </p>
                </div>
                <div
                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-sunken"
                    role="progressbar"
                    :aria-valuenow="bulkProgress.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`${bulkProgress.label} progress`"
                >
                    <div
                        class="h-full rounded-full bg-primary-500 transition-[width] duration-300 motion-reduce:transition-none"
                        :style="{ width: `${bulkProgress.percent}%` }"
                    />
                </div>
                <p class="mt-2 text-[0.82rem] text-muted">
                    You can keep working; this continues in the background.
                </p>
            </div>

            <DataTable
                v-model:selected="selected"
                :rows="leads.data"
                :columns="columns"
                :visible="visible"
                :sort="filters.sort"
                :direction="filters.direction"
                :density="density"
                :loading="loading"
                :row-key-label="(row) => row.name"
                @sort="onSort"
            >
                <template #cell:name="{ row }">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-strong">
                            {{ row.name }}
                        </p>
                        <p
                            v-if="row.email"
                            class="truncate text-[0.82rem] text-muted"
                        >
                            {{ row.email }}
                        </p>
                    </div>
                </template>

                <template #cell:source="{ row }">
                    <span
                        v-if="row.source"
                        class="inline-flex items-center gap-1.5"
                    >
                        {{ row.source }}
                        <!-- §2 requires the provenance of a lead to be visible:
                             an imported list and a verified provider submission
                             must not look the same. -->
                        <ShieldCheck
                            v-if="row.source_authorized"
                            class="size-3.5 text-primary-600"
                            aria-label="From an authorized provider API"
                        />
                    </span>
                    <span v-else class="text-soft">—</span>
                </template>

                <template #cell:status="{ row }">
                    <StatusBadge
                        :tone="row.status_tone as never"
                        :label="row.status_label"
                    />
                </template>

                <template #cell:score="{ row }">
                    <ScoreBadge :score="row.score" />
                </template>

                <template #cell:last_activity_at="{ row }">
                    {{ formatDate(row.last_activity_at) }}
                </template>

                <template #cell:next_follow_up_at="{ row }">
                    {{ formatDate(row.next_follow_up_at) }}
                </template>

                <template #cell:created_at="{ row }">
                    {{ formatDate(row.created_at) }}
                </template>

                <template #actions>
                    <div class="flex items-center justify-end gap-0.5">
                        <button
                            type="button"
                            class="rounded-md p-1.5 text-muted transition-colors hover:bg-surface-alt hover:text-strong"
                            aria-label="View lead"
                        >
                            <Eye class="size-4" />
                        </button>
                        <button
                            v-if="can('lead.update')"
                            type="button"
                            class="rounded-md p-1.5 text-muted transition-colors hover:bg-surface-alt hover:text-strong"
                            aria-label="Edit lead"
                        >
                            <Pencil class="size-4" />
                        </button>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        :title="
                            filters.search || activeFilterCount
                                ? 'No leads match your filters'
                                : 'No leads yet'
                        "
                        :description="
                            filters.search || activeFilterCount
                                ? 'Try a different search term, or clear the filters.'
                                : 'Connect a lead source or create your first lead.'
                        "
                    >
                        <template #actions>
                            <Button
                                v-if="filters.search || activeFilterCount"
                                variant="secondary"
                                size="sm"
                                @click="
                                    navigate({
                                        search: '',
                                        status: [],
                                        owner_id: [],
                                        source_id: [],
                                    })
                                "
                            >
                                Clear filters
                            </Button>
                            <template v-else>
                                <Button variant="primary" size="sm"
                                    >Connect a source</Button
                                >
                                <Button variant="secondary" size="sm"
                                    >Create lead</Button
                                >
                            </template>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>

            <DataTablePagination
                :from="leads.from"
                :to="leads.to"
                :total="leads.total"
                :current-page="leads.current_page"
                :last-page="leads.last_page"
                :per-page="leads.per_page"
                :page-sizes="pageSizes"
                :selected-count="selected.length"
                class="rounded-xl border border-border bg-surface"
                @page="(page) => navigate({ page }, false)"
                @per-page="(size) => navigate({ per_page: size })"
            />
        </div>
    </AppLayout>
</template>
