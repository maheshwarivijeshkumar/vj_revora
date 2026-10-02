<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DataTable, {
    type Column,
    type Density,
} from '@/components/data/DataTable.vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import DataTableToolbar from '@/components/data/DataTableToolbar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type CompanyRow = {
    id: number;
    uuid: string;
    name: string;
    domain: string | null;
    website: string | null;
    industry: string | null;
    size: string | null;
    country: string | null;
    contacts_count: number;
    deals_count: number;
    owner: string | null;
    created_at: string | null;
};

type Paginated = {
    data: CompanyRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = defineProps<{
    companies: Paginated;
    filters: {
        search: string;
        industry: string[];
        owner_id: string[];
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    };
    options?: { owners: SelectOption[]; industries: SelectOption[] };
    pageSizes: number[];
}>();

const { can } = useAuthorization();

const selected = ref<(number | string)[]>([]);
const density = ref<Density>('default');
const filtersOpen = ref(false);
const loading = ref(false);

watch(
    () => props.companies.current_page,
    () => (selected.value = []),
);

const columns: Column<CompanyRow>[] = [
    { key: 'name', label: 'Company', sortable: true },
    { key: 'industry', label: 'Industry', sortable: true },
    // Sorted in the database: "our biggest accounts" is why this list gets
    // opened, and sorting in the browser would only sort the visible page.
    {
        key: 'contacts_count',
        label: 'People',
        sortable: true,
        numeric: true,
        width: '7rem',
    },
    {
        key: 'deals_count',
        label: 'Deals',
        sortable: true,
        numeric: true,
        width: '7rem',
    },
    { key: 'country', label: 'Country', secondary: true },
    { key: 'owner', label: 'Owner', secondary: true },
    { key: 'created_at', label: 'Added', sortable: true, secondary: true },
];

const visible = ref<string[]>([
    'name',
    'industry',
    'contacts_count',
    'deals_count',
    'owner',
]);

const activeFilterCount = computed(
    () => props.filters.industry.length + props.filters.owner_id.length,
);

function navigate(params: Record<string, unknown>, resetPage = true): void {
    loading.value = true;

    router.get(
        '/companies',
        {
            search: props.filters.search,
            industry: props.filters.industry,
            owner_id: props.filters.owner_id,
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
</script>

<template>
    <Head title="Companies" />

    <AppLayout title="Companies" :breadcrumbs="[{ label: 'Companies' }]">
        <template #actions>
            <Button v-if="can('company.create')" variant="primary" size="sm">
                <Plus class="size-4" />
                New company
            </Button>
        </template>

        <div class="space-y-3">
            <DataTableToolbar
                :search="filters.search"
                placeholder="Search name, domain, website…"
                :columns="columns"
                :visible="visible"
                :density="density"
                :active-filter-count="activeFilterCount"
                :can-export="can('company.export')"
                @update:search="(value) => navigate({ search: value })"
                @update:visible="(keys) => (visible = keys)"
                @update:density="(value) => (density = value)"
                @toggle-filters="filtersOpen = !filtersOpen"
            />

            <div
                v-if="filtersOpen"
                class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <template v-if="options">
                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Industry
                        </span>
                        <SelectMenu
                            id="filter-company-industry"
                            :model-value="filters.industry[0] ?? ''"
                            :options="[
                                { value: '', label: 'Any industry' },
                                ...options.industries,
                            ]"
                            @update:model-value="
                                (value) =>
                                    navigate({ industry: value ? [value] : [] })
                            "
                        />
                    </label>

                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Owner
                        </span>
                        <SelectMenu
                            id="filter-company-owner"
                            :model-value="filters.owner_id[0] ?? ''"
                            :options="[
                                { value: '', label: 'Anyone' },
                                ...options.owners,
                            ]"
                            @update:model-value="
                                (value) =>
                                    navigate({ owner_id: value ? [value] : [] })
                            "
                        />
                    </label>

                    <div class="flex items-end">
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="activeFilterCount === 0"
                            @click="navigate({ industry: [], owner_id: [] })"
                        >
                            Clear all
                        </Button>
                    </div>
                </template>

                <p v-else class="text-[0.9rem] text-muted">Loading filters…</p>
            </div>

            <DataTable
                v-model:selected="selected"
                :rows="companies.data"
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
                        <a
                            v-if="row.domain"
                            :href="`https://${row.domain}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 truncate text-[0.82rem] text-muted hover:text-primary-600"
                            @click.stop
                        >
                            {{ row.domain }}
                            <ExternalLink
                                class="size-3 shrink-0"
                                aria-hidden="true"
                            />
                        </a>
                    </div>
                </template>

                <template #cell:industry="{ row }">
                    <span v-if="row.industry">{{ row.industry }}</span>
                    <span v-else class="text-soft">—</span>
                </template>

                <template #cell:country="{ row }">
                    <span v-if="row.country">{{ row.country }}</span>
                    <span v-else class="text-soft">—</span>
                </template>

                <template #cell:created_at="{ row }">
                    {{ formatDate(row.created_at) }}
                </template>

                <template #empty>
                    <EmptyState
                        :title="
                            filters.search || activeFilterCount
                                ? 'No companies match your filters'
                                : 'No companies yet'
                        "
                        :description="
                            filters.search || activeFilterCount
                                ? 'Try a different search term, or clear the filters.'
                                : 'Companies are created as contacts arrive with one, or add one directly.'
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
                                        industry: [],
                                        owner_id: [],
                                    })
                                "
                            >
                                Clear filters
                            </Button>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>

            <DataTablePagination
                :from="companies.from"
                :to="companies.to"
                :total="companies.total"
                :current-page="companies.current_page"
                :last-page="companies.last_page"
                :per-page="companies.per_page"
                :page-sizes="pageSizes"
                :selected-count="selected.length"
                class="rounded-xl border border-border bg-surface"
                @page="(page) => navigate({ page }, false)"
                @per-page="(size) => navigate({ per_page: size })"
            />
        </div>
    </AppLayout>
</template>
