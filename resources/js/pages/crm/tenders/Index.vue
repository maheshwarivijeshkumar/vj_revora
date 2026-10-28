<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink, Landmark, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { useClock } from '@/composables/useClock';
import AppLayout from '@/layouts/AppLayout.vue';

type Tender = {
    id: number;
    source: string;
    source_label: string;
    country: string | null;
    buyer_kind: string;
    buyer_label: string;
    buyer_tone: Tone;
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
        topic_mode: string;
        country: string;
        buyer: string;
        source: string;
        show: 'open' | 'all';
        sort: string;
        direction: 'asc' | 'desc';
        sort_key: string;
        per_page: number;
    };
    topics: { value: string; label: string }[];
    buyers: { value: string; label: string }[];
    sorts: { value: string; label: string }[];
    countries: { value: string; label: string; sources: string[] }[];
    sources: { value: string; label: string }[];
    pageSizes: number[];
    lastFetchedAt: string | null;
    schedule: string;
}>();

const { can } = useAuthorization();
const { formatDateTime } = useClock();
const search = ref(props.filters.search);
const loading = ref(false);
const syncing = ref(false);
const syncSources = ref<string[]>([]);

function portalsFor(country: string): string[] {
    if (country === '') {
        return props.sources.map((source) => source.value);
    }

    return (
        props.countries.find((item) => item.value === country)?.sources ?? []
    );
}

watch(
    () => props.filters.country,
    (country) => {
        syncSources.value = portalsFor(country);
    },
    { immediate: true },
);

/** Table state lives in the URL, so a filtered view is linkable (§111). */
function navigate(params: Record<string, unknown>, resetPage = true): void {
    loading.value = true;

    router.get(
        '/tenders',
        {
            search: props.filters.search,
            topic: props.filters.topic_mode === 'all' ? 'all' : props.filters.topic,
            country: props.filters.country,
            buyer: props.filters.buyer,
            source: props.filters.source,
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

const countryOptions = computed(() => [
    { value: '', label: 'All countries' },
    ...props.countries.map((country) => ({
        value: country.value,
        label: country.label,
    })),
]);

const buyerOptions = computed(() => [
    { value: '', label: 'All buyer types' },
    ...props.buyers,
]);

const specializationOptions = computed(() => [
    { value: 'focused', label: 'Web, software and IT' },
    ...props.topics,
    { value: 'all', label: 'All specializations' },
]);

const portalOptions = computed(() => [
    { value: '', label: 'All portals' },
    ...props.sources.filter((source) =>
        portalsFor(props.filters.country).includes(source.value),
    ),
]);

function setCountry(country: string): void {
    const portals = portalsFor(country);
    const source = portals.includes(props.filters.source)
        ? props.filters.source
        : '';

    navigate({ country, source });
}

function setSpecialization(mode: string): void {
    if (mode === 'all') {
        navigate({ topic: 'all' });

        return;
    }

    if (mode === 'focused') {
        navigate({ topic: ['web', 'software', 'it'] });

        return;
    }

    navigate({ topic: [mode] });
}

function setSort(value: string): void {
    const [sort, direction] = value.split(':');

    navigate({ sort, direction });
}

function toggleSyncSource(source: string, on: boolean): void {
    syncSources.value = on
        ? [...syncSources.value, source]
        : syncSources.value.filter((value) => value !== source);
}

function syncNow(): void {
    syncing.value = true;

    router.post(
        '/tenders/sync',
        { sources: syncSources.value },
        {
            preserveScroll: true,
            onFinish: () => (syncing.value = false),
        },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => navigate({ search: value }), 300);
});

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

    return formatDateTime(value, {
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
                Public, semi-government, private and private-industry notices.
                Automatic sync runs at {{ schedule }}.
                <template v-if="lastFetchedAt">
                    Last updated {{ formatDeadline(lastFetchedAt) }}.
                </template>
            </p>

            <div class="rounded-xl border border-border bg-surface p-4">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <FormField id="tender-search" label="Search">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted"
                                aria-hidden="true"
                            />
                            <TextInput
                                id="tender-search"
                                v-model="search"
                                type="text"
                                class="!pl-9"
                                placeholder="Title, tender no, organization, city…"
                            />
                        </div>
                    </FormField>

                    <FormField id="tender-country" label="Country">
                        <SelectMenu
                            id="tender-country"
                            :model-value="filters.country"
                            :options="countryOptions"
                            :search-threshold="0"
                            search-placeholder="Search countries"
                            @update:model-value="setCountry"
                        />
                    </FormField>

                    <FormField id="tender-buyer" label="Buyer type">
                        <SelectMenu
                            id="tender-buyer"
                            :model-value="filters.buyer"
                            :options="buyerOptions"
                            @update:model-value="
                                (value) => navigate({ buyer: value })
                            "
                        />
                    </FormField>

                    <FormField id="tender-specialization" label="Specialization">
                        <SelectMenu
                            id="tender-specialization"
                            :model-value="filters.topic_mode"
                            :options="specializationOptions"
                            @update:model-value="setSpecialization"
                        />
                    </FormField>

                    <FormField id="tender-portal" label="Portal">
                        <SelectMenu
                            id="tender-portal"
                            :model-value="filters.source"
                            :options="portalOptions"
                            @update:model-value="
                                (value) => navigate({ source: value })
                            "
                        />
                    </FormField>

                    <FormField id="tender-sort" label="Sort">
                        <SelectMenu
                            id="tender-sort"
                            :model-value="filters.sort_key"
                            :options="sorts"
                            @update:model-value="setSort"
                        />
                    </FormField>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
                >
                    <label class="flex items-center gap-2 text-[0.85rem] text-body">
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
                    <p class="text-[0.82rem] text-muted">
                        {{ tenders.total }}
                        {{ tenders.total === 1 ? 'notice' : 'notices' }}
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
                class="overflow-hidden rounded-xl border border-border bg-surface"
                :class="{ 'opacity-60': loading }"
            >
                <EmptyState
                    v-if="tenders.data.length === 0"
                    title="No tenders match"
                    description="Try another country, buyer type or specialization, or include closed notices."
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
                                    <span class="sr-only">Open the notice</span>
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
                                            :tone="tender.buyer_tone"
                                            :label="tender.buyer_label"
                                        />
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
                                        v-if="tender.city || tender.country"
                                        class="text-[0.78rem] text-muted"
                                    >
                                        {{
                                            [tender.city, tender.country]
                                                .filter(Boolean)
                                                .join(', ')
                                        }}
                                    </p>
                                    <p class="text-[0.78rem] text-muted">
                                        {{ tender.source_label }}
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
                                            >{{ tender.reference }} on
                                            {{ tender.source_label }}
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
