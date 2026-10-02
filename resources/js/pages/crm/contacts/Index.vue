<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, Mail, Phone, Plus } from 'lucide-vue-next';
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

type ContactRow = {
    id: number;
    uuid: string;
    name: string;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    company: string | null;
    companies_count: number;
    owner: string | null;
    created_at: string | null;
};

type Paginated = {
    data: ContactRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = defineProps<{
    contacts: Paginated;
    filters: {
        search: string;
        owner_id: string[];
        company_id: string[];
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    };
    options?: { owners: SelectOption[]; companies: SelectOption[] };
    pageSizes: number[];
}>();

const { can } = useAuthorization();

const selected = ref<(number | string)[]>([]);
const density = ref<Density>('default');
const filtersOpen = ref(false);
const loading = ref(false);

// Selection is per page, so it must not survive a page change: acting on rows
// the user can no longer see is how bulk actions go wrong (§113).
watch(
    () => props.contacts.current_page,
    () => (selected.value = []),
);

const columns: Column<ContactRow>[] = [
    { key: 'name', label: 'Contact', sortable: true },
    { key: 'job_title', label: 'Role', sortable: true },
    { key: 'company', label: 'Company' },
    { key: 'phone', label: 'Phone', secondary: true },
    { key: 'owner', label: 'Owner', secondary: true },
    { key: 'created_at', label: 'Added', sortable: true, secondary: true },
];

const visible = ref<string[]>([
    'name',
    'job_title',
    'company',
    'phone',
    'owner',
]);

const activeFilterCount = computed(
    () => props.filters.owner_id.length + props.filters.company_id.length,
);

/**
 * Pushes table state into the URL, so a filtered view is linkable, survives the
 * back button, and the server stays the single source of truth for it (§111).
 */
function navigate(params: Record<string, unknown>, resetPage = true): void {
    loading.value = true;

    router.get(
        '/contacts',
        {
            search: props.filters.search,
            owner_id: props.filters.owner_id,
            company_id: props.filters.company_id,
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
    <Head title="Contacts" />

    <AppLayout title="Contacts" :breadcrumbs="[{ label: 'Contacts' }]">
        <template #actions>
            <Button v-if="can('contact.create')" variant="primary" size="sm">
                <Plus class="size-4" />
                New contact
            </Button>
        </template>

        <div class="space-y-3">
            <DataTableToolbar
                :search="filters.search"
                placeholder="Search name, email, phone, role…"
                :columns="columns"
                :visible="visible"
                :density="density"
                :active-filter-count="activeFilterCount"
                :can-export="can('contact.export')"
                @update:search="(value) => navigate({ search: value })"
                @update:visible="(keys) => (visible = keys)"
                @update:density="(value) => (density = value)"
                @toggle-filters="filtersOpen = !filtersOpen"
            />

            <!-- Options are deferred, so the panel shows a placeholder rather
                 than blocking the table on data the table does not need. -->
            <div
                v-if="filtersOpen"
                class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <template v-if="options">
                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Owner
                        </span>
                        <SelectMenu
                            id="filter-contact-owner"
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

                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Company
                        </span>
                        <SelectMenu
                            id="filter-contact-company"
                            :model-value="filters.company_id[0] ?? ''"
                            :options="[
                                { value: '', label: 'Any company' },
                                ...options.companies,
                            ]"
                            @update:model-value="
                                (value) =>
                                    navigate({
                                        company_id: value ? [value] : [],
                                    })
                            "
                        />
                    </label>

                    <div class="flex items-end">
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="activeFilterCount === 0"
                            @click="navigate({ owner_id: [], company_id: [] })"
                        >
                            Clear all
                        </Button>
                    </div>
                </template>

                <p v-else class="text-[0.9rem] text-muted">Loading filters…</p>
            </div>

            <DataTable
                v-model:selected="selected"
                :rows="contacts.data"
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
                            class="flex items-center gap-1 truncate text-[0.82rem] text-muted"
                        >
                            <Mail class="size-3 shrink-0" aria-hidden="true" />
                            {{ row.email }}
                        </p>
                    </div>
                </template>

                <template #cell:company="{ row }">
                    <span
                        v-if="row.company"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Building2
                            class="size-3.5 shrink-0 text-soft"
                            aria-hidden="true"
                        />
                        {{ row.company }}
                        <!-- Someone who has worked at several places keeps that
                             history, so the extra count is shown rather than
                             dropped (§21). -->
                        <span
                            v-if="row.companies_count > 1"
                            class="text-[0.78rem] text-soft"
                        >
                            +{{ row.companies_count - 1 }}
                        </span>
                    </span>
                    <span v-else class="text-soft">—</span>
                </template>

                <template #cell:phone="{ row }">
                    <span
                        v-if="row.phone"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Phone
                            class="size-3.5 shrink-0 text-soft"
                            aria-hidden="true"
                        />
                        {{ row.phone }}
                    </span>
                    <span v-else class="text-soft">—</span>
                </template>

                <template #cell:created_at="{ row }">
                    {{ formatDate(row.created_at) }}
                </template>

                <template #empty>
                    <EmptyState
                        :title="
                            filters.search || activeFilterCount
                                ? 'No contacts match your filters'
                                : 'No contacts yet'
                        "
                        :description="
                            filters.search || activeFilterCount
                                ? 'Try a different search term, or clear the filters.'
                                : 'Contacts appear here as leads convert, or add one directly.'
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
                                        owner_id: [],
                                        company_id: [],
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
                :from="contacts.from"
                :to="contacts.to"
                :total="contacts.total"
                :current-page="contacts.current_page"
                :last-page="contacts.last_page"
                :per-page="contacts.per_page"
                :page-sizes="pageSizes"
                :selected-count="selected.length"
                class="rounded-xl border border-border bg-surface"
                @page="(page) => navigate({ page }, false)"
                @per-page="(size) => navigate({ per_page: size })"
            />
        </div>
    </AppLayout>
</template>
