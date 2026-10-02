<script setup lang="ts" generic="TCard extends KanbanCard">
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';

export type KanbanCard = { id: number | string };

export type KanbanColumn<TCard extends KanbanCard> = {
    id: number | string;
    name: string;
    total: number;
    cards: TCard[];
    /** Rendered under the column name, for a value roll-up or similar. */
    meta?: string;
    tone?: 'default' | 'won' | 'lost';
};

const props = defineProps<{
    columns: KanbanColumn<TCard>[];
    /** Disables dragging, for a read-only viewer. */
    readonly?: boolean;
}>();

const emit = defineEmits<{
    move: [
        payload: {
            cardId: number | string;
            columnId: number | string;
            position: number;
        },
    ];
}>();

/**
 * Kanban board (§22).
 *
 * Dragging uses native HTML5 drag events, which are pointer-only. Keyboard
 * users get an explicit "move to" menu on each card instead — see the `card`
 * slot's `move` callback. Relying on drag alone would make the board
 * unusable without a mouse, which §115 does not allow.
 *
 * Reordering is optimistic: the card moves under the cursor immediately and
 * the server response either confirms it or replaces the board.
 */
const dragging = ref<{ cardId: number | string; from: number | string } | null>(
    null,
);
const overColumn = ref<number | string | null>(null);
const overIndex = ref<number | null>(null);

const isDragging = computed(() => dragging.value !== null);

function onDragStart(
    event: DragEvent,
    cardId: number | string,
    columnId: number | string,
): void {
    if (props.readonly) {
        return;
    }

    dragging.value = { cardId, from: columnId };

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        // Firefox refuses to start a drag unless some data is set.
        event.dataTransfer.setData('text/plain', String(cardId));
    }
}

function onDragEnd(): void {
    dragging.value = null;
    overColumn.value = null;
    overIndex.value = null;
}

function onDragOverCard(
    event: DragEvent,
    columnId: number | string,
    index: number,
): void {
    if (!isDragging.value) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    // Dropping on the lower half of a card means "after it", which is what
    // makes the insertion line land where the cursor actually is.
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const after = event.clientY > rect.top + rect.height / 2;

    overColumn.value = columnId;
    overIndex.value = after ? index + 1 : index;
}

function onDragOverColumn(event: DragEvent, column: KanbanColumn<TCard>): void {
    if (!isDragging.value) {
        return;
    }

    event.preventDefault();
    overColumn.value = column.id;

    // Empty space below the cards means the end of the column.
    if (overIndex.value === null) {
        overIndex.value = column.cards.length;
    }
}

function onDrop(event: DragEvent, column: KanbanColumn<TCard>): void {
    event.preventDefault();

    const current = dragging.value;
    const index = overIndex.value ?? column.cards.length;

    onDragEnd();

    if (!current) {
        return;
    }

    // A card dropped exactly where it started is not a move.
    const source = props.columns.find((c) => c.id === current.from);
    const originalIndex =
        source?.cards.findIndex((card) => card.id === current.cardId) ?? -1;

    if (
        current.from === column.id &&
        (originalIndex === index || originalIndex === index - 1)
    ) {
        return;
    }

    emit('move', {
        cardId: current.cardId,
        columnId: column.id,
        position: index,
    });
}

/** The keyboard path: move a card to the end of another column. */
function moveTo(cardId: number | string, columnId: number | string): void {
    const column = props.columns.find((c) => c.id === columnId);
    emit('move', { cardId, columnId, position: column?.cards.length ?? 0 });
}

function showsInsertionLine(columnId: number | string, index: number): boolean {
    return (
        isDragging.value &&
        overColumn.value === columnId &&
        overIndex.value === index
    );
}

const TONES = {
    default: 'bg-border-strong',
    won: 'bg-emerald-500',
    lost: 'bg-red-400',
};
</script>

<template>
    <div class="flex scrollbar-thin gap-4 overflow-x-auto pb-4">
        <section
            v-for="column in columns"
            :key="column.id"
            class="flex w-[19rem] shrink-0 flex-col rounded-xl border border-border bg-surface-alt"
            :class="
                overColumn === column.id &&
                isDragging &&
                'ring-2 ring-primary-500/40'
            "
            :aria-label="`${column.name}, ${column.total} deals`"
            @dragover="onDragOverColumn($event, column)"
            @drop="onDrop($event, column)"
        >
            <header
                class="flex items-center gap-2 border-b border-border px-3.5 py-3"
            >
                <span
                    class="size-2 shrink-0 rounded-full"
                    :class="TONES[column.tone ?? 'default']"
                    aria-hidden="true"
                />
                <h3
                    class="min-w-0 flex-1 truncate text-[0.92rem] font-semibold text-strong"
                >
                    {{ column.name }}
                </h3>
                <span
                    class="rounded-full bg-surface px-2 py-0.5 text-[0.75rem] font-medium text-muted tabular-nums"
                >
                    {{ column.total }}
                </span>
            </header>

            <p
                v-if="column.meta"
                class="px-3.5 pt-2 text-[0.78rem] text-muted tabular-nums"
            >
                {{ column.meta }}
            </p>

            <div class="flex-1 scrollbar-thin space-y-2 overflow-y-auto p-2.5">
                <template v-for="(card, index) in column.cards" :key="card.id">
                    <div
                        v-if="showsInsertionLine(column.id, index)"
                        class="h-0.5 rounded-full bg-primary-600"
                        aria-hidden="true"
                    />

                    <div
                        :draggable="!readonly"
                        :class="
                            cn(
                                'rounded-lg border border-border bg-surface p-3 transition-shadow',
                                !readonly &&
                                    'cursor-grab active:cursor-grabbing',
                                dragging?.cardId === card.id && 'opacity-40',
                            )
                        "
                        @dragstart="onDragStart($event, card.id, column.id)"
                        @dragend="onDragEnd"
                        @dragover="onDragOverCard($event, column.id, index)"
                    >
                        <slot
                            name="card"
                            :card="card"
                            :column="column"
                            :move="
                                (target: number | string) =>
                                    moveTo(card.id, target)
                            "
                        />
                    </div>
                </template>

                <div
                    v-if="showsInsertionLine(column.id, column.cards.length)"
                    class="h-0.5 rounded-full bg-primary-600"
                    aria-hidden="true"
                />

                <p
                    v-if="column.cards.length === 0"
                    class="rounded-lg border border-dashed border-border px-3 py-8 text-center text-[0.85rem] text-soft"
                >
                    Nothing here yet
                </p>

                <p
                    v-else-if="column.total > column.cards.length"
                    class="px-1 pt-1 text-center text-[0.78rem] text-soft"
                >
                    Showing {{ column.cards.length }} of {{ column.total }}
                </p>
            </div>
        </section>
    </div>
</template>
