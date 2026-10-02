<script setup lang="ts">
import {
    Check,
    Columns3,
    Download,
    ListFilter,
    Rows3,
    Search,
    X,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import type { Column, Density } from '@/components/data/DataTable.vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        search: string;
        placeholder?: string;
        columns: Column<never>[];
        visible: string[];
        density: Density;
        activeFilterCount?: number;
        canExport?: boolean;
    }>(),
    {
        placeholder: 'Search…',
        activeFilterCount: 0,
        canExport: false,
    },
);

const emit = defineEmits<{
    'update:search': [value: string];
    'update:visible': [keys: string[]];
    'update:density': [density: Density];
    toggleFilters: [];
    export: [];
}>();

const local = ref(props.search);
const columnsOpen = ref(false);
const densityOpen = ref(false);
const root = ref<HTMLElement | null>(null);

let debounce: ReturnType<typeof setTimeout> | undefined;

// Debounced so a server-side search does not fire a request per keystroke
// (§111). 300ms is long enough to finish a word, short enough to feel live.
watch(local, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => emit('update:search', value), 300);
});

// Keeps the input in step when the URL changes from outside, such as the
// browser back button restoring a previous search.
watch(
    () => props.search,
    (value) => {
        if (value !== local.value) {
            local.value = value;
        }
    },
);

function clearSearch(): void {
    local.value = '';
    clearTimeout(debounce);
    emit('update:search', '');
}

function toggleColumn(key: string, checked: boolean): void {
    emit(
        'update:visible',
        checked
            ? props.columns
                  .filter((c) => c.key === key || props.visible.includes(c.key))
                  .map((c) => c.key)
            : props.visible.filter((k) => k !== key),
    );
}

const DENSITIES: { value: Density; label: string }[] = [
    { value: 'comfortable', label: 'Comfortable' },
    { value: 'default', label: 'Default' },
    { value: 'compact', label: 'Compact' },
];

function onPointerDown(event: PointerEvent): void {
    if (!root.value?.contains(event.target as Node)) {
        columnsOpen.value = false;
        densityOpen.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', onPointerDown));
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    clearTimeout(debounce);
});
</script>

<template>
    <div ref="root" class="flex flex-wrap items-center gap-2">
        <!-- Search leads, per §111. -->
        <div class="relative min-w-0 flex-1 sm:max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-soft"
                aria-hidden="true"
            />
            <input
                v-model="local"
                type="search"
                :placeholder="placeholder"
                :aria-label="placeholder"
                class="h-9 w-full rounded-lg border border-border bg-surface pr-8 pl-9 text-[0.9rem] text-strong placeholder:text-soft"
            />
            <button
                v-if="local"
                type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-soft transition-colors hover:text-strong"
                aria-label="Clear search"
                @click="clearSearch"
            >
                <X class="size-3.5" />
            </button>
        </div>

        <Button variant="secondary" size="sm" @click="emit('toggleFilters')">
            <ListFilter class="size-4" />
            Filters
            <span
                v-if="activeFilterCount > 0"
                class="ml-0.5 rounded-full bg-primary-600 px-1.5 text-[0.7rem] font-semibold text-white tabular-nums"
            >
                {{ activeFilterCount }}
            </span>
        </Button>

        <!-- Column visibility (§111). -->
        <div class="relative">
            <Button
                variant="secondary"
                size="sm"
                @click="
                    columnsOpen = !columnsOpen;
                    densityOpen = false;
                "
            >
                <Columns3 class="size-4" />
                <span class="hidden sm:inline">Columns</span>
            </Button>

            <div
                v-if="columnsOpen"
                class="absolute right-0 z-30 mt-1.5 w-56 rounded-lg border border-border bg-surface p-1.5 shadow-modal"
            >
                <p
                    class="px-2.5 py-1.5 text-[0.7rem] font-semibold tracking-[0.1em] text-soft uppercase"
                >
                    Show columns
                </p>
                <label
                    v-for="column in columns"
                    :key="column.key"
                    class="flex cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[0.9rem] text-body hover:bg-surface-alt"
                >
                    <Checkbox
                        :id="`col-${column.key}`"
                        :model-value="visible.includes(column.key)"
                        @update:model-value="
                            (checked) => toggleColumn(column.key, checked)
                        "
                    />
                    {{ column.label }}
                </label>
            </div>
        </div>

        <!-- Density (§111). -->
        <div class="relative">
            <Button
                variant="secondary"
                size="sm"
                @click="
                    densityOpen = !densityOpen;
                    columnsOpen = false;
                "
            >
                <Rows3 class="size-4" />
                <span class="sr-only">Row density</span>
            </Button>

            <div
                v-if="densityOpen"
                class="absolute right-0 z-30 mt-1.5 w-44 rounded-lg border border-border bg-surface p-1.5 shadow-modal"
            >
                <button
                    v-for="option in DENSITIES"
                    :key="option.value"
                    type="button"
                    :class="
                        cn(
                            'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-[0.9rem] hover:bg-surface-alt',
                            density === option.value
                                ? 'text-strong'
                                : 'text-muted',
                        )
                    "
                    @click="
                        emit('update:density', option.value);
                        densityOpen = false;
                    "
                >
                    <Check
                        class="size-3.5 text-primary-600"
                        :class="
                            density === option.value
                                ? 'opacity-100'
                                : 'opacity-0'
                        "
                        aria-hidden="true"
                    />
                    {{ option.label }}
                </button>
            </div>
        </div>

        <Button
            v-if="canExport"
            variant="secondary"
            size="sm"
            @click="emit('export')"
        >
            <Download class="size-4" />
            <span class="hidden sm:inline">Export</span>
        </Button>

        <slot name="actions" />
    </div>
</template>
