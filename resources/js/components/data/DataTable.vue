<script setup lang="ts" generic="TRow extends { id: number | string }">
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import { cn } from '@/lib/utils';

export type Column<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    /** Right-align numeric columns so digits line up. */
    numeric?: boolean;
    /** Hidden below `lg`, for columns that are useful but not essential. */
    secondary?: boolean;
    width?: string;
    accessor?: (row: T) => unknown;
};

export type Density = 'comfortable' | 'default' | 'compact';

const props = withDefaults(
    defineProps<{
        rows: TRow[];
        columns: Column<TRow>[];
        /** Column keys currently visible, in display order. */
        visible: string[];
        selected: (number | string)[];
        sort?: string;
        direction?: 'asc' | 'desc';
        loading?: boolean;
        density?: Density;
        /** Set when a request failed, so the table can offer a retry (§114). */
        error?: string | null;
        rowKeyLabel?: (row: TRow) => string;
    }>(),
    {
        loading: false,
        density: 'default',
        error: null,
        sort: undefined,
        direction: 'desc',
    },
);

const emit = defineEmits<{
    'update:selected': [ids: (number | string)[]];
    sort: [key: string];
    rowClick: [row: TRow];
}>();

const shown = computed(() =>
    props.visible
        .map((key) => props.columns.find((column) => column.key === key))
        .filter((column): column is Column<TRow> => column !== undefined),
);

const allSelected = computed(
    () => props.rows.length > 0 && props.selected.length === props.rows.length,
);

/**
 * True when some but not all rows are selected.
 *
 * The header checkbox must show this third state, or "select all" becomes a
 * lie about what is currently selected (§110.1).
 */
const someSelected = computed(
    () =>
        props.selected.length > 0 && props.selected.length < props.rows.length,
);

const PADDING: Record<Density, string> = {
    comfortable: 'px-4 py-3.5',
    default: 'px-4 py-2.5',
    compact: 'px-3 py-1.5',
};

const cellPadding = computed(() => PADDING[props.density]);

function toggleAll(checked: boolean): void {
    emit('update:selected', checked ? props.rows.map((row) => row.id) : []);
}

function toggleRow(id: number | string, checked: boolean): void {
    emit(
        'update:selected',
        checked
            ? [...props.selected, id]
            : props.selected.filter((selectedId) => selectedId !== id),
    );
}

function isSelected(id: number | string): boolean {
    return props.selected.includes(id);
}

function value(row: TRow, column: Column<TRow>): unknown {
    return column.accessor
        ? column.accessor(row)
        : (row as Record<string, unknown>)[column.key];
}

function sortIcon(column: Column<TRow>) {
    if (props.sort !== column.key) {
        return ChevronsUpDown;
    }
    return props.direction === 'asc' ? ArrowUp : ArrowDown;
}

/** What a screen reader announces for a sortable header (§115). */
function ariaSort(column: Column<TRow>): 'ascending' | 'descending' | 'none' {
    if (props.sort !== column.key) {
        return 'none';
    }
    return props.direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        <!-- Horizontal scroll rather than shrinking text until the table is
             unusable (§115). -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-border bg-surface-alt">
                        <!-- Row selection is always the first column where
                             bulk actions exist (§110.1). -->
                        <th scope="col" :class="cn(cellPadding, 'w-10')">
                            <Checkbox
                                id="dt-select-all"
                                :model-value="allSelected"
                                :indeterminate="someSelected"
                                @update:model-value="toggleAll"
                            />
                            <label for="dt-select-all" class="sr-only">
                                Select all rows on this page
                            </label>
                        </th>

                        <th
                            v-for="column in shown"
                            :key="column.key"
                            scope="col"
                            :aria-sort="
                                column.sortable ? ariaSort(column) : undefined
                            "
                            :style="
                                column.width
                                    ? { width: column.width }
                                    : undefined
                            "
                            :class="
                                cn(
                                    cellPadding,
                                    'text-[0.78rem] font-semibold whitespace-nowrap text-muted',
                                    column.numeric && 'text-right',
                                    column.secondary && 'hidden lg:table-cell',
                                )
                            "
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1.5 rounded transition-colors hover:text-strong',
                                        column.numeric && 'flex-row-reverse',
                                    )
                                "
                                @click="emit('sort', column.key)"
                            >
                                {{ column.label }}
                                <component
                                    :is="sortIcon(column)"
                                    class="size-3.5"
                                    :class="
                                        sort === column.key
                                            ? 'text-strong'
                                            : 'text-soft'
                                    "
                                    aria-hidden="true"
                                />
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>

                        <th
                            scope="col"
                            :class="cn(cellPadding, 'w-20 text-right')"
                        >
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Skeleton rows rather than a blank page (§114). -->
                    <tr
                        v-if="loading"
                        v-for="n in 8"
                        :key="`skeleton-${n}`"
                        class="border-b border-border-soft last:border-0"
                    >
                        <td :class="cellPadding">
                            <Skeleton class="size-5" />
                        </td>
                        <td
                            v-for="column in shown"
                            :key="column.key"
                            :class="
                                cn(
                                    cellPadding,
                                    column.secondary && 'hidden lg:table-cell',
                                )
                            "
                        >
                            <Skeleton
                                class="h-4"
                                :class="
                                    column.numeric ? 'ml-auto w-12' : 'w-28'
                                "
                            />
                        </td>
                        <td :class="cellPadding">
                            <Skeleton class="ml-auto h-4 w-10" />
                        </td>
                    </tr>

                    <tr v-else-if="error">
                        <td :colspan="shown.length + 2" class="px-4 py-16">
                            <slot name="error" :message="error" />
                        </td>
                    </tr>

                    <tr v-else-if="rows.length === 0">
                        <td :colspan="shown.length + 2" class="px-4 py-4">
                            <slot name="empty" />
                        </td>
                    </tr>

                    <tr
                        v-else
                        v-for="row in rows"
                        :key="row.id"
                        :class="
                            cn(
                                'border-b border-border-soft transition-colors last:border-0',
                                isSelected(row.id)
                                    ? 'bg-primary-50/60 dark:bg-primary-900/20'
                                    : 'hover:bg-surface-alt',
                            )
                        "
                    >
                        <td :class="cellPadding" @click.stop>
                            <Checkbox
                                :id="`dt-row-${row.id}`"
                                :model-value="isSelected(row.id)"
                                @update:model-value="
                                    (checked) => toggleRow(row.id, checked)
                                "
                            />
                            <label :for="`dt-row-${row.id}`" class="sr-only">
                                Select
                                {{
                                    rowKeyLabel
                                        ? rowKeyLabel(row)
                                        : `row ${row.id}`
                                }}
                            </label>
                        </td>

                        <td
                            v-for="column in shown"
                            :key="column.key"
                            :class="
                                cn(
                                    cellPadding,
                                    'text-[0.9rem] text-body',
                                    column.numeric && 'text-right tabular-nums',
                                    column.secondary && 'hidden lg:table-cell',
                                )
                            "
                            @click="emit('rowClick', row)"
                        >
                            <slot
                                :name="`cell:${column.key}`"
                                :row="row"
                                :value="value(row, column)"
                            >
                                {{ value(row, column) ?? '—' }}
                            </slot>
                        </td>

                        <td :class="cn(cellPadding, 'text-right')" @click.stop>
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
