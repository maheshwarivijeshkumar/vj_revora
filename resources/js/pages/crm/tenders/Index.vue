<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink, Landmark, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Tender = {
    id: number;
    reference: string;
    title: string;
    buyer_reference: string | null;
    organization: string | null;
    department: string | null;
    city: string | null;
    sector: string | null;
    category: string | null;
    tender_type: string | null;
    status: string | null;
    topic: string;
    topic_label: string;
    topic_tone: Tone;
    advertised_on: string | null;
    closing_at: string | null;
    is_open: boolean;
    is_new: boolean;
    url: string;
};

type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

const props = defineProps<{
    tenders: Paginated<Tender>;
    filters: {
        search: string;
        topic: string[];
        show: 'open' | 'all';
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    };
    topics: { value: string; label: string }[];
    pageSizes: number[];
    lastFetchedAt: string | null;
}>();

const search = ref(props.filters.search);
const loading = ref(false);

/** Table state lives in the URL, so a filtered view is linkable (§111). */
function navigate(params: Record<string, unknown>, resetPage = true): void {
    loading.value = true;

    router.get(
        '/tenders',
        {
            search: props.filters.search,
            topic: props.filters.topic,
            show: props.filters.show,
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

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => navigate({ search: value }), 300);
});

function toggleTopic(topic: string): void {
    const topics = props.filters.topic.includes(topic)
        ? props.filters.topic.filter((t) => t !== topic)
        : [...props.filters.topic, topic];

    navigate({ topic: topics });
}

function sortBy(column: string): void {
    const direction =
        props.filters.sort === column && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';

    navigate({ sort: column, direction });
}

function formatDay(value: string | null): string {
    if (!value) {
        return '—';
    }

    // A calendar date: parsed as local so it is not shifted a day.
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatDeadline(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function daysLeft(tender: Tender): { label: string; tone: Tone } {
    if (!tender.closing_at) {
        return { label: 'No deadline', tone: 'neutral' };
    }

    const days = Math.ceil(
        (new Date(tender.closing_at).getTime() - Date.now()) / 86_400_000,
    );

    if (days < 0) {
        return { label: 'Closed', tone: 'neutral' };
    }

    if (days <= 3) {
        return {
            label: days === 0 ? 'Closes today' : `${days}d left`,
            tone: 'danger',
        };
    }

    return { label: `${days}d left`, tone: days <= 7 ? 'warning' : 'success' };
}
</script>

<template>
    <Head title="Tenders" />

    <AppLayout title="Tenders" :breadcrumbs="[{ label: 'Tenders' }]">
        <div class="space-y-3">
            <p class="max-w-3xl text-[0.88rem] text-muted">
                Government of Pakistan tenders from PPRA, fetched twice a day (8
                AM and 4 PM Pakistan time).
                <template v-if="lastFetchedAt">
                    Last updated {{ formatDeadline(lastFetchedAt) }}.
                </template>
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-sm">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
                        aria-hidden="true"
                    />
                    <TextInput
                        id="tender-search"
                        v-model="search"
                        type="text"
                        class="pl-9"
                        placeholder="Search title, tender no, organization, city…"
                        aria-label="Search tenders"
                    />
                </div>

                <div
                    class="flex flex-wrap gap-1.5"
                    role="group"
                    aria-label="Tender type"
                >
                    <button
                        v-for="topic in topics"
                        :key="topic.value"
                        type="button"
                        class="rounded-full border px-3 py-1 text-[0.82rem] font-medium transition-colors"
                        :class="
                            filters.topic.includes(topic.value)
                                ? 'border-primary-600 bg-primary-600 text-white'
                                : 'border-border bg-surface text-body hover:bg-surface-alt'
                        "
                        :aria-pressed="filters.topic.includes(topic.value)"
                        @click="toggleTopic(topic.value)"
                    >
                        {{ topic.label }}
                    </button>
                </div>

                <label
                    class="ml-auto flex items-center gap-2 text-[0.85rem] text-body"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border-border"
                        :checked="filters.show === 'all'"
                        @change="
                            navigate({
                                show: filters.show === 'all' ? 'open' : 'all',
                            })
                        "
                    />
                    Include closed
                </label>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-border bg-surface"
                :class="{ 'opacity-60': loading }"
            >
                <EmptyState
                    v-if="tenders.data.length === 0"
                    title="No tenders match"
                    description="Try another search, include more types, or include closed tenders. New tenders arrive twice a day."
                >
                    <template #icon>
                        <Landmark class="size-5" />
                    </template>
                </EmptyState>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-[0.86rem]">
                        <thead
                            class="border-b border-border text-[0.75rem] text-soft uppercase"
                        >
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">
                                    Tender
                                </th>
                                <th class="px-3 py-2.5 font-semibold">
                                    Organization
                                </th>
                                <th class="px-3 py-2.5 font-semibold">
                                    <button
                                        type="button"
                                        class="uppercase hover:text-strong"
                                        @click="sortBy('advertised_on')"
                                    >
                                        Issued
                                    </button>
                                </th>
                                <th class="px-3 py-2.5 font-semibold">
                                    <button
                                        type="button"
                                        class="uppercase hover:text-strong"
                                        @click="sortBy('closing_at')"
                                    >
                                        Submission deadline
                                    </button>
                                </th>
                                <th class="px-4 py-2.5">
                                    <span class="sr-only">Open on PPRA</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="tender in tenders.data"
                                :key="tender.id"
                                class="align-top hover:bg-surface-alt"
                            >
                                <td class="max-w-xl px-4 py-3">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <StatusBadge
                                            :tone="tender.topic_tone"
                                            :label="tender.topic_label"
                                        />
                                        <StatusBadge
                                            v-if="tender.is_new"
                                            tone="new"
                                            label="New"
                                        />
                                        <StatusBadge
                                            v-if="
                                                tender.status &&
                                                tender.status !== 'Published'
                                            "
                                            tone="warning"
                                            :label="tender.status"
                                        />
                                    </div>
                                    <p class="mt-1 font-medium text-strong">
                                        {{ tender.title }}
                                    </p>
                                    <p class="mt-0.5 text-[0.78rem] text-muted">
                                        {{ tender.reference }}
                                        <template v-if="tender.buyer_reference">
                                            · {{ tender.buyer_reference }}
                                        </template>
                                        <template v-if="tender.category">
                                            · {{ tender.category }}
                                        </template>
                                        <template v-if="tender.tender_type">
                                            · {{ tender.tender_type }}
                                        </template>
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="text-strong">
                                        {{
                                            tender.department ??
                                            tender.organization ??
                                            '—'
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            tender.department &&
                                            tender.organization
                                        "
                                        class="text-[0.78rem] text-muted"
                                    >
                                        {{ tender.organization }}
                                    </p>
                                    <p
                                        v-if="tender.city"
                                        class="text-[0.78rem] text-muted"
                                    >
                                        {{ tender.city }}
                                    </p>
                                </td>
                                <td
                                    class="px-3 py-3 whitespace-nowrap text-body"
                                >
                                    {{ formatDay(tender.advertised_on) }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <p class="text-body">
                                        {{ formatDeadline(tender.closing_at) }}
                                    </p>
                                    <StatusBadge
                                        class="mt-1"
                                        :tone="daysLeft(tender).tone"
                                        :label="daysLeft(tender).label"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        :href="tender.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-[0.85rem] font-medium whitespace-nowrap text-primary-600 hover:underline"
                                    >
                                        View
                                        <ExternalLink
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span class="sr-only"
                                            >{{ tender.reference }} on PPRA
                                            (opens in a new tab)</span
                                        >
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <DataTablePagination
                v-if="tenders.total > 0"
                :from="tenders.from"
                :to="tenders.to"
                :total="tenders.total"
                :current-page="tenders.current_page"
                :last-page="tenders.last_page"
                :per-page="tenders.per_page"
                :page-sizes="pageSizes"
                @page="(page) => navigate({ page }, false)"
                @per-page="(size) => navigate({ per_page: size })"
            />
        </div>
    </AppLayout>
</template>
