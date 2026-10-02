<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    Clock,
    CornerDownLeft,
    Handshake,
    Loader2,
    Search,
    Users,
    UserPlus,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { useUiStore } from '@/stores/ui';

type Hit = {
    id: string;
    title: string;
    subtitle: string | null;
    badge: string | null;
    href: string;
};

type Group = { key: string; label: string; hits: Hit[] };

/** A row the user can act on, flattened for keyboard navigation. */
type Row = {
    key: string;
    title: string;
    subtitle: string | null;
    href: string;
};

const MIN_LENGTH = 2;
const DEBOUNCE_MS = 250;
const RECENT_KEY = 'palette-recent';
const RECENT_LIMIT = 5;

const ui = useUiStore();
const { can } = useAuthorization();

const term = ref('');
const groups = ref<Group[]>([]);
const loading = ref(false);
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
const recent = ref<string[]>(readRecent());

let timer: ReturnType<typeof setTimeout> | undefined;
/** Discards a response that arrives after the user has typed something else. */
let inFlight = 0;

const GROUP_ICONS: Record<string, typeof Users> = {
    leads: UserPlus,
    contacts: Users,
    companies: Building2,
    deals: Handshake,
};

/**
 * What the user can start from here (§120).
 *
 * Permission-filtered client-side purely so the list is honest; the routes
 * behind them are gated on the server, which is the actual boundary.
 */
const actions = computed(() =>
    [
        { label: 'Go to leads', href: '/leads', permission: 'lead.view' },
        { label: 'Go to deal board', href: '/deals', permission: 'deal.view' },
        { label: 'Go to dashboard', href: '/dashboard', permission: null },
        {
            label: 'API keys',
            href: '/settings/api-keys',
            permission: 'api.view',
        },
        {
            label: 'Webhooks',
            href: '/settings/webhooks',
            permission: 'webhook.view',
        },
        {
            label: 'Audit log',
            href: '/settings/audit',
            permission: 'audit.view',
        },
    ].filter((a) => a.permission === null || can(a.permission)),
);

const isSearching = computed(() => term.value.trim().length >= MIN_LENGTH);

/**
 * Headed sections, each carrying the index its first row has in the flat list.
 *
 * Results stay grouped because "the Acme company" and "the Acme deal" are
 * different answers to the same word (§45), but keyboard navigation has to run
 * over one sequence or Up/Down would jump in an order the user cannot see. The
 * offset is what keeps the two views of the same list in step.
 */
const sections = computed(() => {
    if (!isSearching.value) {
        return [
            {
                key: 'actions',
                label: 'Go to',
                rows: actions.value.map((a) => ({
                    key: `action:${a.href}`,
                    title: a.label,
                    subtitle: null,
                    href: a.href,
                })),
                offset: 0,
            },
        ];
    }

    let offset = 0;

    return groups.value.map((group) => {
        const section = {
            key: group.key,
            label: group.label,
            rows: group.hits.map((hit) => ({
                key: `${group.key}:${hit.id}`,
                title: hit.title,
                subtitle: hit.subtitle,
                href: hit.href,
            })),
            offset,
        };

        offset += section.rows.length;

        return section;
    });
});

const rows = computed<Row[]>(() =>
    sections.value.flatMap((section) => section.rows),
);

const activeRow = computed<Row | undefined>(
    () => rows.value[activeIndex.value],
);

watch(
    () => ui.commandPaletteOpen,
    async (open) => {
        if (!open) {
            reset();
            return;
        }

        recent.value = readRecent();
        await nextTick();
        input.value?.focus();
    },
);

watch(term, (value) => {
    activeIndex.value = 0;

    if (timer) {
        clearTimeout(timer);
    }

    if (value.trim().length < MIN_LENGTH) {
        groups.value = [];
        loading.value = false;
        return;
    }

    // Debounced: a request per keystroke would put four queries per character
    // on the database and still show the same answer (§45).
    loading.value = true;
    timer = setTimeout(() => void run(value), DEBOUNCE_MS);
});

async function run(value: string): Promise<void> {
    const ticket = ++inFlight;

    try {
        const response = await fetch(`/search?q=${encodeURIComponent(value)}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const payload = (await response.json()) as { groups: Group[] };

        // A slower earlier request must not overwrite a newer answer.
        if (ticket !== inFlight) {
            return;
        }

        groups.value = payload.groups;
    } catch {
        if (ticket === inFlight) {
            groups.value = [];
        }
    } finally {
        if (ticket === inFlight) {
            loading.value = false;
        }
    }
}

function move(delta: number): void {
    const total = rows.value.length;

    if (total === 0) {
        return;
    }

    // Wraps, because reaching the end and stopping reads as a broken key.
    activeIndex.value = (activeIndex.value + delta + total) % total;
}

function choose(row: Row | undefined): void {
    if (!row) {
        return;
    }

    if (isSearching.value) {
        remember(term.value.trim());
    }

    ui.closeCommandPalette();
    router.visit(row.href);
}

function searchFor(value: string): void {
    term.value = value;
    input.value?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        choose(activeRow.value);
    }
}

function reset(): void {
    term.value = '';
    groups.value = [];
    activeIndex.value = 0;
    loading.value = false;
}

function readRecent(): string[] {
    try {
        const raw = localStorage.getItem(RECENT_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed.filter((v): v is string => typeof v === 'string')
            : [];
    } catch {
        // Private window, blocked site data, or malformed JSON. Recent searches
        // are a convenience, so the palette works without them.
        return [];
    }
}

function remember(value: string): void {
    if (value.length < MIN_LENGTH) {
        return;
    }

    const next = [value, ...recent.value.filter((v) => v !== value)].slice(
        0,
        RECENT_LIMIT,
    );
    recent.value = next;

    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
        /* Not persisted; the list still works for this session. */
    }
}

function iconFor(key: string) {
    return GROUP_ICONS[key] ?? Search;
}
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-150 motion-reduce:transition-none"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-100 motion-reduce:transition-none"
        leave-to-class="opacity-0"
    >
        <div
            v-if="ui.commandPaletteOpen"
            class="fixed inset-0 z-[60] flex items-start justify-center p-4 pt-[12vh]"
        >
            <div
                class="absolute inset-0 bg-[var(--overlay)]"
                @click="ui.closeCommandPalette()"
            />

            <div
                class="relative w-full max-w-xl overflow-hidden rounded-xl border border-border bg-surface shadow-lg"
                role="dialog"
                aria-modal="true"
                aria-label="Search and commands"
            >
                <!-- Input -->
                <div
                    class="flex items-center gap-2.5 border-b border-border px-4 py-3"
                >
                    <Search
                        class="size-4 shrink-0 text-soft"
                        aria-hidden="true"
                    />
                    <input
                        ref="input"
                        v-model="term"
                        type="text"
                        class="min-w-0 flex-1 bg-transparent text-[0.95rem] text-strong outline-none placeholder:text-soft"
                        placeholder="Search leads, contacts, companies, deals…"
                        role="combobox"
                        aria-expanded="true"
                        aria-controls="palette-results"
                        :aria-activedescendant="
                            activeRow ? `palette-row-${activeIndex}` : undefined
                        "
                        autocomplete="off"
                        spellcheck="false"
                        @keydown="onKeydown"
                    />
                    <Loader2
                        v-if="loading"
                        class="size-4 shrink-0 animate-spin text-soft motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                </div>

                <div
                    id="palette-results"
                    role="listbox"
                    aria-label="Results"
                    class="max-h-[22rem] scrollbar-thin overflow-y-auto py-1.5"
                >
                    <!-- Recent searches, before anything is typed -->
                    <template v-if="!isSearching && recent.length">
                        <p
                            class="px-4 pt-1.5 pb-1 text-[0.72rem] font-semibold tracking-wide text-soft uppercase"
                        >
                            Recent
                        </p>
                        <button
                            v-for="value in recent"
                            :key="value"
                            type="button"
                            class="flex w-full items-center gap-2.5 px-4 py-1.5 text-left text-[0.9rem] text-muted transition-colors hover:bg-surface-alt"
                            @click="searchFor(value)"
                        >
                            <Clock
                                class="size-3.5 shrink-0 text-soft"
                                aria-hidden="true"
                            />
                            {{ value }}
                        </button>
                    </template>

                    <p
                        v-if="isSearching && !loading && rows.length === 0"
                        class="px-4 py-8 text-center text-[0.9rem] text-muted"
                    >
                        Nothing matches “{{ term }}”.
                    </p>

                    <template
                        v-for="(section, si) in sections"
                        :key="section.key"
                    >
                        <p
                            v-if="section.rows.length"
                            class="px-4 pb-1 text-[0.72rem] font-semibold tracking-wide text-soft uppercase"
                            :class="si === 0 ? 'pt-1.5' : 'pt-3'"
                        >
                            {{ section.label }}
                        </p>

                        <button
                            v-for="(row, i) in section.rows"
                            :id="`palette-row-${section.offset + i}`"
                            :key="row.key"
                            type="button"
                            role="option"
                            :aria-selected="section.offset + i === activeIndex"
                            class="flex w-full items-center gap-2.5 px-4 py-2 text-left transition-colors"
                            :class="
                                section.offset + i === activeIndex
                                    ? 'dark:bg-primary-950/40 bg-primary-50'
                                    : 'hover:bg-surface-alt'
                            "
                            @mouseenter="activeIndex = section.offset + i"
                            @click="choose(row)"
                        >
                            <component
                                :is="iconFor(section.key)"
                                class="size-4 shrink-0 text-soft"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-[0.92rem] text-strong"
                                >
                                    {{ row.title }}
                                </span>
                                <span
                                    v-if="row.subtitle"
                                    class="block truncate text-[0.8rem] text-muted"
                                >
                                    {{ row.subtitle }}
                                </span>
                            </span>
                            <CornerDownLeft
                                v-if="section.offset + i === activeIndex"
                                class="size-3.5 shrink-0 text-soft"
                                aria-hidden="true"
                            />
                        </button>
                    </template>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center gap-3 border-t border-border bg-surface-alt px-4 py-2 text-[0.75rem] text-soft"
                >
                    <span class="flex items-center gap-1">
                        <kbd
                            class="rounded border border-border bg-surface px-1 py-0.5"
                            >↑</kbd
                        >
                        <kbd
                            class="rounded border border-border bg-surface px-1 py-0.5"
                            >↓</kbd
                        >
                        to navigate
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd
                            class="rounded border border-border bg-surface px-1 py-0.5"
                            >↵</kbd
                        >
                        to open
                    </span>
                    <span class="ml-auto flex items-center gap-1">
                        <kbd
                            class="rounded border border-border bg-surface px-1 py-0.5"
                            >esc</kbd
                        >
                        to close
                    </span>
                </div>
            </div>
        </div>
    </Transition>
</template>
