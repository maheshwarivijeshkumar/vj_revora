<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, Mail, Pencil, Phone, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch, onMounted } from 'vue';
import ContactFormDrawer, {
    type EditableContact,
} from '@/components/crm/ContactFormDrawer.vue';
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
    first_name: string | null;
    last_name: string | null;
    country: string | null;
    owner_id: number | null;
    company_id: number | null;
    company_role: string | null;
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

/** The record the drawer is editing: null creates, undefined means closed. */
const editing = ref<EditableContact | null | undefined>(undefined);

/**
 * Opens the create drawer when arrived at from the global Create menu.
 *
 * Read once on mount and then dropped from the URL, so a refresh or a shared
 * link does not reopen a form the person has already dealt with.
 */
onMounted(() => {
    const url = new URL(window.location.href);

    if (url.searchParams.get('new') === null) {
        return;
    }

    createContact();
    url.searchParams.delete('new');
    window.history.replaceState({}, '', url.toString());
});

function createContact(): void {
    editing.value = null;
}

function editContact(row: ContactRow): void {
    editing.value = {
        id: row.id,
        first_name: row.first_name,
        last_name: row.last_name,
        email: row.email,
        phone: row.phone,
        job_title: row.job_title,
        country: row.country,
        owner_id: row.owner_id,
        company_id: row.company_id,
        company_role: row.company_role,
    };
}

function deleteContact(row: ContactRow): void {
    // Names the record: a delete fired from a row of icons is easy to aim at
    // the wrong one (§113).
    if (!window.confirm(`Delete ${row.name}? It can be restored later.`)) {
        return;
    }

    router.delete(`/contacts/${row.id}`, { preserveScroll: true });
}

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
            <Button
                v-if="can('contact.create')"
                variant="primary"
                size="sm"
                @click="createContact"
            >
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

                <template #actions="{ row }">
                    <div class="flex items-center justify-end gap-0.5">
                        <button
                            v-if="can('contact.update')"
                            type="button"
                            class="rounded-md p-1.5 text-muted transition-colors hover:bg-surface-alt hover:text-strong"
                            :aria-label="`Edit ${row.name}`"
                            @click="editContact(row)"
                        >
                            <Pencil class="size-4" />
                        </button>
                        <button
                            v-if="can('contact.delete')"
                            type="button"
                            class="rounded-md p-1.5 text-muted transition-colors hover:bg-danger-soft hover:text-danger"
                            :aria-label="`Delete ${row.name}`"
                            @click="deleteContact(row)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
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
                            <Button
                                v-else-if="can('contact.create')"
                                variant="primary"
                                size="sm"
                                @click="createContact"
                            >
                                Create the first contact
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

        <ContactFormDrawer
            :open="editing !== undefined"
            :contact="editing ?? null"
            :options="options"
            @close="editing = undefined"
        />
    </AppLayout>
</template>
