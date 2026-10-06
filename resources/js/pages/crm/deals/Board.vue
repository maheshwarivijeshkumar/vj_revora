<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, MoveRight, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import DealFormDrawer, {
    type EditableDeal,
} from '@/components/crm/DealFormDrawer.vue';
import KanbanBoard, {
    type KanbanColumn,
} from '@/components/data/KanbanBoard.vue';
import Button from '@/components/ui/Button.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { useToastStore } from '@/stores/toast';

type DealCard = {
    id: number;
    uuid: string;
    title: string;
    value: number;
    currency: string;
    probability: number;
    status: string;
    owner: string | null;
    company: string | null;
    expected_close_date: string | null;
    owner_id: number | null;
    company_id: number | null;
    contact_id: number | null;
    lost_reason: string | null;
    stage_name: string;
};

type Stage = {
    id: number;
    name: string;
    key: string;
    probability: number;
    is_won: boolean;
    is_lost: boolean;
    required_fields: string[];
    total: number;
    value: number;
    cards: DealCard[];
};

const props = defineProps<{
    pipeline: { id: number; name: string };
    pipelines: SelectOption[];
    stages: Stage[];
    cardLimit: number;
    options?: {
        owners: SelectOption[];
        companies: SelectOption[];
        contacts: SelectOption[];
        currencies: SelectOption[];
    };
}>();

const toasts = useToastStore();
const { can } = useAuthorization();

const moving = ref(false);

/** The deal the drawer is editing: null creates, undefined means closed. */
const editing = ref<EditableDeal | null | undefined>(undefined);

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

    createDeal();
    url.searchParams.delete('new');
    window.history.replaceState({}, '', url.toString());
});

function createDeal(): void {
    editing.value = null;
}

function editDeal(card: DealCard): void {
    editing.value = {
        id: card.id,
        title: card.title,
        value: card.value,
        currency: card.currency,
        probability: card.probability,
        expected_close_date: card.expected_close_date,
        owner_id: card.owner_id,
        company_id: card.company_id,
        contact_id: card.contact_id,
        lost_reason: card.lost_reason,
        stage_name: card.stage_name,
    };
}

function deleteDeal(card: DealCard): void {
    // Names the deal and its value: a card is a small target and this is not
    // reversible from the board (§113).
    if (
        !window.confirm(
            `Delete “${card.title}” (${money(card.value)})? It can be restored later.`,
        )
    ) {
        return;
    }

    router.delete(`/deals/${card.id}`, { preserveScroll: true });
}

const columns = computed<KanbanColumn<DealCard>[]>(() =>
    props.stages.map((stage) => ({
        id: stage.id,
        name: stage.name,
        total: stage.total,
        cards: stage.cards,
        meta: stage.total > 0 ? money(stage.value) : undefined,
        tone: stage.is_won ? 'won' : stage.is_lost ? 'lost' : 'default',
    })),
);

const forecast = computed(() =>
    props.stages
        .filter((stage) => !stage.is_won && !stage.is_lost)
        .reduce(
            (total, stage) => total + stage.value * (stage.probability / 100),
            0,
        ),
);

function money(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
        notation: value >= 100_000 ? 'compact' : 'standard',
    }).format(value);
}

/**
 * Persists a drop.
 *
 * The board is re-read from the server on success rather than patched in
 * place: the move may have renumbered two columns, changed the deal's
 * probability and closed it, and reproducing all of that client-side would be
 * a second implementation of the same rules.
 */
function onMove(payload: {
    cardId: number | string;
    columnId: number | string;
    position: number;
}): void {
    moving.value = true;

    router.post(
        `/deals/${payload.cardId}/move`,
        { stage_id: payload.columnId, position: payload.position },
        {
            preserveScroll: true,
            onError: (errors) => {
                // A stage can refuse a deal that is missing required fields
                // (§22). Saying which field is missing is the difference
                // between a useful error and a shrug (§59).
                toasts.error('That move was not allowed', {
                    description: errors.stage_id ?? 'Please try again.',
                });
            },
            onFinish: () => (moving.value = false),
        },
    );
}

function switchPipeline(id: string): void {
    router.get('/deals', { pipeline: id }, { preserveScroll: true });
}

/** Stage options for the keyboard move menu on each card. */
const stageOptions = computed(() =>
    props.stages.map((stage) => ({ id: stage.id, name: stage.name })),
);
</script>

<template>
    <Head title="Deals" />

    <AppLayout title="Deals" :breadcrumbs="[{ label: 'Deals' }]">
        <template #actions>
            <div class="w-52">
                <SelectMenu
                    id="pipeline-switcher"
                    :model-value="String(pipeline.id)"
                    :options="pipelines"
                    @update:model-value="switchPipeline"
                />
            </div>
            <Button
                v-if="can('deal.create')"
                variant="primary"
                size="sm"
                @click="createDeal"
            >
                <Plus class="size-4" />
                New deal
            </Button>
        </template>

        <div class="space-y-4">
            <!-- Weighted forecast, which is the number a sales manager opens
                 this page to see. Open stages only: won and lost are outcomes,
                 not pipeline. -->
            <div
                class="flex flex-wrap items-center gap-x-8 gap-y-2 rounded-xl border border-border bg-surface px-4 py-3"
            >
                <div>
                    <p class="text-[0.78rem] text-muted">Weighted pipeline</p>
                    <p class="text-h3 font-bold text-strong tabular-nums">
                        {{ money(forecast) }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.78rem] text-muted">Open deals</p>
                    <p class="text-h3 font-bold text-strong tabular-nums">
                        {{
                            stages
                                .filter((s) => !s.is_won && !s.is_lost)
                                .reduce((n, s) => n + s.total, 0)
                        }}
                    </p>
                </div>
                <p v-if="moving" class="ml-auto text-[0.85rem] text-muted">
                    Saving…
                </p>
            </div>

            <KanbanBoard
                :columns="columns"
                :readonly="!can('deal.update')"
                @move="onMove"
            >
                <template #card="{ card, move }">
                    <p
                        class="text-[0.9rem] font-medium text-balance text-strong"
                    >
                        {{ card.title }}
                    </p>

                    <p
                        v-if="card.company"
                        class="mt-1 flex items-center gap-1.5 text-[0.8rem] text-muted"
                    >
                        <Building2
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ card.company }}</span>
                    </p>

                    <div class="mt-2.5 flex items-center justify-between gap-2">
                        <span
                            class="text-[0.95rem] font-semibold text-strong tabular-nums"
                        >
                            {{ money(card.value) }}
                        </span>
                        <span class="text-[0.78rem] text-muted tabular-nums">
                            {{ card.probability }}%
                        </span>
                    </div>

                    <div
                        v-if="card.owner"
                        class="mt-2 flex items-center gap-2 border-t border-border-soft pt-2"
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full text-[0.6rem] font-semibold text-primary-700 dark:text-primary-200"
                            :style="{ background: 'var(--brand-soft)' }"
                            aria-hidden="true"
                        >
                            {{
                                card.owner
                                    .split(' ')
                                    .map((p: string) => p[0])
                                    .join('')
                                    .slice(0, 2)
                            }}
                        </span>
                        <span class="truncate text-[0.78rem] text-muted">{{
                            card.owner
                        }}</span>
                    </div>

                    <div class="mt-2 flex items-center gap-1">
                        <!--
                          The keyboard path. Native HTML5 drag is pointer-only,
                          so without this the board would be unusable without a
                          mouse (§115).
                        -->
                        <details v-if="can('deal.update')" class="group flex-1">
                            <summary
                                class="flex cursor-pointer list-none items-center gap-1 text-[0.78rem] text-muted transition-colors hover:text-strong"
                            >
                                <MoveRight
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Move to
                            </summary>
                            <ul class="mt-1.5 space-y-0.5">
                                <li
                                    v-for="stage in stageOptions"
                                    :key="stage.id"
                                >
                                    <button
                                        type="button"
                                        class="w-full rounded px-2 py-1 text-left text-[0.8rem] text-body transition-colors hover:bg-surface-alt disabled:opacity-40"
                                        @click="move(stage.id)"
                                    >
                                        {{ stage.name }}
                                    </button>
                                </li>
                            </ul>
                        </details>

                        <button
                            v-if="can('deal.update')"
                            type="button"
                            class="rounded p-1 text-muted transition-colors hover:bg-surface-alt hover:text-strong"
                            :aria-label="`Edit ${card.title}`"
                            @click.stop="editDeal(card)"
                        >
                            <Pencil class="size-3.5" />
                        </button>
                        <button
                            v-if="can('deal.delete')"
                            type="button"
                            class="rounded p-1 text-muted transition-colors hover:bg-danger-soft hover:text-danger"
                            :aria-label="`Delete ${card.title}`"
                            @click.stop="deleteDeal(card)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </template>
            </KanbanBoard>
        </div>

        <DealFormDrawer
            :open="editing !== undefined"
            :deal="editing ?? null"
            :pipeline-id="pipeline.id"
            :options="options"
            @close="editing = undefined"
        />
    </AppLayout>
</template>
