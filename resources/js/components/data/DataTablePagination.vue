<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Table footer (§112).
 *
 * Shows the total, the current range, a page-size selector and
 * first/previous/next/last controls with correct disabled states.
 */
const props = defineProps<{
    from: number | null;
    to: number | null;
    total: number;
    currentPage: number;
    lastPage: number;
    perPage: number;
    pageSizes: number[];
    selectedCount?: number;
}>();

const emit = defineEmits<{ page: [page: number]; perPage: [size: number] }>();

const isFirst = computed(() => props.currentPage <= 1);
const isLast = computed(() => props.currentPage >= props.lastPage);

/**
 * A short window of page numbers with ellipses rather than every page.
 * At five hundred pages a full list is unusable.
 */
const pages = computed<(number | 'gap')[]>(() => {
    const current = props.currentPage;
    const last = props.lastPage;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const window: (number | 'gap')[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        window.push('gap');
    }
    for (let page = start; page <= end; page++) {
        window.push(page);
    }
    if (end < last - 1) {
        window.push('gap');
    }

    window.push(last);

    return window;
});
</script>

<template>
    <div
        class="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-center gap-4 text-[0.85rem] text-muted">
            <p>
                <template v-if="total > 0">
                    Showing
                    <span class="font-medium text-strong tabular-nums">
                        {{ from }}&ndash;{{ to }}
                    </span>
                    of
                    <span class="font-medium text-strong tabular-nums">
                        {{ total.toLocaleString() }}
                    </span>
                </template>
                <template v-else>No records</template>
            </p>

            <p v-if="selectedCount" class="font-medium text-strong">
                {{ selectedCount }} selected
            </p>
        </div>

        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-[0.85rem] text-muted">
                <span class="hidden sm:inline">Rows</span>
                <select
                    :value="perPage"
                    class="h-8 rounded-md border border-border bg-surface px-2 text-[0.85rem] text-strong"
                    aria-label="Rows per page"
                    @change="
                        emit(
                            'perPage',
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                >
                    <option v-for="size in pageSizes" :key="size" :value="size">
                        {{ size }}
                    </option>
                </select>
            </label>

            <nav class="flex items-center gap-0.5" aria-label="Pagination">
                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-alt hover:text-strong disabled:pointer-events-none disabled:opacity-40"
                    :disabled="isFirst"
                    aria-label="First page"
                    @click="emit('page', 1)"
                >
                    <ChevronsLeft class="size-4" />
                </button>
                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-alt hover:text-strong disabled:pointer-events-none disabled:opacity-40"
                    :disabled="isFirst"
                    aria-label="Previous page"
                    @click="emit('page', currentPage - 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>

                <template v-for="(page, i) in pages" :key="`${page}-${i}`">
                    <span v-if="page === 'gap'" class="px-1 text-soft">…</span>
                    <button
                        v-else
                        type="button"
                        :class="
                            cn(
                                'flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-[0.85rem] tabular-nums transition-colors',
                                page === currentPage
                                    ? 'bg-primary-600 font-medium text-white'
                                    : 'text-muted hover:bg-surface-alt hover:text-strong',
                            )
                        "
                        :aria-current="
                            page === currentPage ? 'page' : undefined
                        "
                        @click="emit('page', page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-alt hover:text-strong disabled:pointer-events-none disabled:opacity-40"
                    :disabled="isLast"
                    aria-label="Next page"
                    @click="emit('page', currentPage + 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-alt hover:text-strong disabled:pointer-events-none disabled:opacity-40"
                    :disabled="isLast"
                    aria-label="Last page"
                    @click="emit('page', lastPage)"
                >
                    <ChevronsRight class="size-4" />
                </button>
            </nav>
        </div>
    </div>
</template>
