<script setup lang="ts" generic="TRow extends { id: number | string }">
import { Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import BulkActionBar from '@/components/data/BulkActionBar.vue';
import DataTable, { type Column, type Density } from '@/components/data/DataTable.vue';
import DataTableToolbar from '@/components/data/DataTableToolbar.vue';
import ConfirmDialog from '@/components/overlay/ConfirmDialog.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useRecordDelete } from '@/composables/useRecordDelete';

const props = withDefaults(
    defineProps<{
        rows: TRow[];
        columns: Column<TRow>[];
        emptyTitle: string;
        emptyDescription?: string;
        placeholder?: string;
        /** Server already filtered these rows. */
        remoteSearch?: boolean;
        search?: string;
        resource?: string;
        canDelete?: boolean;
        noun?: string;
        plural?: string;
        rowKeyLabel?: (row: TRow) => string;
    }>(),
    {
        placeholder: 'Search…',
        remoteSearch: false,
        search: '',
        resource: '',
        canDelete: false,
        noun: 'record',
        plural: 'records',
    },
);

const emit = defineEmits<{ search: [value: string] }>();

const deletion = useRecordDelete(
    () => props.resource,
    () => props.noun,
    () => props.plural,
);

const localSearch = ref(props.search);
const density = ref<Density>('default');
const visible = ref<string[]>([]);

watch(
    () => props.columns.map((column) => column.key).join('|'),
    () => {
        visible.value = props.columns.map((column) => column.key);
    },
    { immediate: true },
);

watch(
    () => props.search,
    (value) => {
        if (props.remoteSearch && value !== localSearch.value) {
            localSearch.value = value;
        }
    },
);

watch(
    () => props.rows.map((row) => row.id).join('|'),
    () => {
        const ids = new Set(props.rows.map((row) => row.id));
        deletion.selected.value = deletion.selected.value.filter((id) => ids.has(id));
    },
);

const filtered = computed(() => {
    if (props.remoteSearch) {
        return props.rows;
    }

    const needle = localSearch.value.trim().toLowerCase();

    if (needle === '') {
        return props.rows;
    }

    return props.rows.filter((row) =>
        Object.values(row).some((value) => String(value ?? '').toLowerCase().includes(needle)),
    );
});

const searching = computed(() => localSearch.value.trim() !== '');

function onSearch(value: string): void {
    localSearch.value = value;

    if (props.remoteSearch) {
        emit('search', value);
    }
}

function label(row: TRow): string {
    if (props.rowKeyLabel) {
        return props.rowKeyLabel(row);
    }

    const record = row as Record<string, unknown>;

    return String(record.name ?? record.title ?? record.number ?? row.id);
}
</script>

<template>
    <div class="space-y-3">
        <DataTableToolbar
            :search="localSearch"
            :placeholder="placeholder"
            :columns="columns"
            :visible="visible"
            :density="density"
            @update:search="onSearch"
            @update:visible="visible = $event"
            @update:density="density = $event"
        />

        <p class="text-[0.82rem] text-muted tabular-nums">
            {{ filtered.length }}
            {{ filtered.length === 1 ? noun : plural }}
        </p>

        <BulkActionBar
            v-if="canDelete"
            :count="deletion.selected.value.length"
            @clear="deletion.selected.value = []"
        >
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="text-danger"
                @click="deletion.ask(deletion.selected.value)"
            >
                <Trash2 class="size-4" />
                Delete {{ deletion.selected.value.length }}
            </Button>
        </BulkActionBar>

        <DataTable
            :rows="filtered"
            :columns="columns"
            :visible="visible"
            :selected="canDelete ? deletion.selected.value : []"
            :density="density"
            :selectable="canDelete"
            :actions="canDelete || $slots.actions !== undefined"
            :row-key-label="label"
            @update:selected="deletion.selected.value = $event"
        >
            <template v-for="column in columns" :key="column.key" #[`cell:${column.key}`]="slotProps">
                <slot :name="`cell:${column.key}`" v-bind="slotProps" />
            </template>

            <template #actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <slot name="actions" :row="row" />
                    <Button
                        v-if="canDelete"
                        type="button"
                        variant="danger"
                        size="icon"
                        :aria-label="`Delete ${label(row)}`"
                        @click="deletion.ask([row.id])"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </template>

            <template #empty>
                <EmptyState
                    :title="searching ? 'No matches' : emptyTitle"
                    :description="
                        searching
                            ? 'Nothing in this list matches that search.'
                            : emptyDescription
                    "
                />
            </template>
        </DataTable>

        <ConfirmDialog
            :open="deletion.open.value"
            :title="deletion.title.value"
            :message="deletion.message.value"
            :busy="deletion.busy.value"
            @cancel="deletion.cancel()"
            @confirm="deletion.confirm()"
        />
    </div>
</template>
