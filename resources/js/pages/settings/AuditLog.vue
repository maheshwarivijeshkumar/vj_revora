<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    KeyRound,
    Server,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTablePagination from '@/components/data/DataTablePagination.vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const { timeZone } = useClock();
type Entry = {
    id: number;
    action: string;
    action_label: string;
    is_destructive: boolean;
    actor: { type: string; name: string };
    entity: { type: string; id: number | string | null } | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip: string | null;
    user_agent: string | null;
    created_at: string | null;
};

const props = defineProps<{
    logs: {
        data: Entry[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            from: number | null;
            to: number | null;
            total: number;
        };
    };
    actions: { value: string; label: string; group: string }[];
    actors: { value: number; label: string }[];
    filters: {
        action?: string[];
        actor?: number | string;
        from?: string;
        to?: string;
    };
}>();

const expanded = ref<number | null>(null);

const action = ref(props.filters.action?.[0] ?? '');
const actor = ref(String(props.filters.actor ?? ''));
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const actionOptions = computed(() => [
    { value: '', label: 'All actions' },
    ...props.actions.map((a) => ({
        value: a.value,
        label: a.label,
        group: a.group,
        note: a.value,
    })),
]);

const actorOptions = computed(() => [
    { value: '', label: 'Anyone' },
    ...props.actors.map((a) => ({ value: String(a.value), label: a.label })),
]);

const hasFilters = computed(() =>
    Boolean(action.value || actor.value || from.value || to.value),
);

/** Filtering happens in the database (§112); the page only carries the query. */
function apply(): void {
    router.get(
        '/settings/audit',
        {
            ...(action.value ? { action: [action.value] } : {}),
            ...(actor.value ? { actor: actor.value } : {}),
            ...(from.value ? { from: from.value } : {}),
            ...(to.value ? { to: to.value } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clear(): void {
    action.value = '';
    actor.value = '';
    from.value = '';
    to.value = '';
    apply();
}

function page(target: number): void {
    router.get(
        '/settings/audit',
        { ...props.filters, page: target },
        { preserveState: true, preserveScroll: true },
    );
}

function actorIcon(type: string) {
    return { user: User, api_key: KeyRound }[type] ?? Server;
}

/** Only fields with something to show; an all-null diff is noise. */
function diffKeys(entry: Entry): string[] {
    return [
        ...new Set([
            ...Object.keys(entry.before ?? {}),
            ...Object.keys(entry.after ?? {}),
        ]),
    ];
}

function cell(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString(undefined, { timeZone: timeZone.value,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}
</script>

<template>
    <Head title="Audit log" />

    <AppLayout
        title="Audit log"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Audit log' }]"
    >
        <div class="space-y-4">
            <form
                class="rounded-xl border border-border bg-surface p-4"
                @submit.prevent="apply"
            >
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField id="audit-action" label="Action">
                        <SelectMenu
                            id="audit-action"
                            v-model="action"
                            :options="actionOptions"
                            placeholder="All actions"
                            @update:model-value="apply"
                        />
                    </FormField>
                    <FormField id="audit-actor" label="Who">
                        <SelectMenu
                            id="audit-actor"
                            v-model="actor"
                            :options="actorOptions"
                            placeholder="Anyone"
                            @update:model-value="apply"
                        />
                    </FormField>
                    <FormField id="audit-from" label="From">
                        <TextInput
                            id="audit-from"
                            v-model="from"
                            type="date"
                            @change="apply"
                        />
                    </FormField>
                    <FormField id="audit-to" label="To">
                        <TextInput
                            id="audit-to"
                            v-model="to"
                            type="date"
                            @change="apply"
                        />
                    </FormField>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[0.85rem] text-muted">
                        {{ logs.meta.total.toLocaleString() }}
                        {{ logs.meta.total === 1 ? 'entry' : 'entries' }}
                    </p>
                    <Button
                        v-if="hasFilters"
                        variant="secondary"
                        size="lg"
                        type="button"
                        @click="clear"
                    >
                        Clear filters
                    </Button>
                </div>
            </form>

            <!-- Trail -->
            <div
                v-if="logs.data.length === 0"
                class="rounded-xl border border-border bg-surface px-4 py-12 text-center"
            >
                <p class="text-[0.95rem] font-medium text-strong">
                    Nothing recorded yet
                </p>
                <p class="mx-auto mt-1 max-w-md text-[0.88rem] text-muted">
                    {{
                        hasFilters
                            ? 'No entries match these filters.'
                            : 'Sign-ins, record changes and integration changes will appear here as they happen.'
                    }}
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <div
                    class="hidden grid-cols-[1.25rem_minmax(9rem,1.15fr)_minmax(7rem,0.8fr)_minmax(8rem,1fr)_minmax(11rem,1.15fr)_6.5rem] items-center gap-3 border-b border-border bg-surface-alt px-4 py-2.5 text-[0.75rem] font-semibold tracking-wide text-muted uppercase sm:grid"
                >
                    <span aria-hidden="true"></span>
                    <span>Action</span>
                    <span>Record</span>
                    <span>Who</span>
                    <span>When</span>
                    <span>IP</span>
                </div>
                <ul class="divide-y divide-border">
                    <li v-for="entry in logs.data" :key="entry.id">
                        <button
                            type="button"
                            class="grid w-full items-center gap-2 px-4 py-3 text-left transition-colors hover:bg-surface-alt sm:grid-cols-[1.25rem_minmax(9rem,1.15fr)_minmax(7rem,0.8fr)_minmax(8rem,1fr)_minmax(11rem,1.15fr)_6.5rem] sm:gap-3"
                            :aria-expanded="expanded === entry.id"
                            @click="
                                expanded =
                                    expanded === entry.id ? null : entry.id
                            "
                        >
                            <component
                                :is="
                                    expanded === entry.id
                                        ? ChevronDown
                                        : ChevronRight
                                "
                                class="size-4 shrink-0 text-soft"
                                aria-hidden="true"
                            />

                            <span class="min-w-0">
                                <StatusBadge
                                    :tone="
                                        entry.is_destructive
                                            ? 'danger'
                                            : 'neutral'
                                    "
                                    :label="entry.action_label"
                                />
                            </span>
                            <span
                                class="min-w-0 truncate font-mono text-[0.78rem] text-muted"
                            >
                                {{
                                    entry.entity
                                        ? `${entry.entity.type} #${entry.entity.id}`
                                        : '—'
                                }}
                            </span>
                            <span
                                class="flex min-w-0 items-center gap-1.5 text-[0.85rem] text-strong"
                            >
                                <component
                                    :is="actorIcon(entry.actor.type)"
                                    class="size-3.5 shrink-0 text-muted"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{
                                    entry.actor.name
                                }}</span>
                            </span>
                            <span
                                class="text-[0.85rem] text-muted tabular-nums"
                                >{{ formatDate(entry.created_at) }}</span
                            >
                            <span
                                class="truncate font-mono text-[0.78rem] text-muted"
                                >{{ entry.ip ?? '—' }}</span
                            >
                        </button>

                        <!-- The diff -->
                        <div
                            v-if="expanded === entry.id"
                            class="border-t border-border-soft bg-surface-alt px-4 py-3"
                        >
                            <table
                                v-if="diffKeys(entry).length"
                                class="w-full text-left text-[0.85rem]"
                            >
                                <thead>
                                    <tr
                                        class="text-[0.75rem] text-soft uppercase"
                                    >
                                        <th class="pb-1.5 font-semibold">
                                            Field
                                        </th>
                                        <th class="pb-1.5 font-semibold">
                                            Before
                                        </th>
                                        <th class="pb-1.5 font-semibold">
                                            After
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="key in diffKeys(entry)"
                                        :key="key"
                                        class="align-top"
                                    >
                                        <td
                                            class="py-1 pr-4 font-mono text-[0.8rem] text-strong"
                                        >
                                            {{ key }}
                                        </td>
                                        <td
                                            class="py-1 pr-4 break-all text-muted"
                                        >
                                            {{ cell(entry.before?.[key]) }}
                                        </td>
                                        <td class="py-1 break-all text-strong">
                                            {{ cell(entry.after?.[key]) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="text-[0.85rem] text-muted">
                                No field changes recorded for this entry.
                            </p>

                            <p
                                v-if="entry.user_agent"
                                class="mt-3 font-mono text-[0.75rem] break-all text-soft"
                            >
                                {{ entry.user_agent }}
                            </p>
                        </div>
                    </li>
                </ul>

                <DataTablePagination
                    :from="logs.meta.from"
                    :to="logs.meta.to"
                    :total="logs.meta.total"
                    :current-page="logs.meta.current_page"
                    :last-page="logs.meta.last_page"
                    :per-page="logs.meta.per_page"
                    :page-sizes="[25]"
                    @page="page"
                />
            </div>

            <p class="text-[0.85rem] text-muted">
                Entries cannot be edited or removed. Passwords, signing secrets
                and API keys are never recorded, only the fact that they
                changed.
            </p>
        </div>
    </AppLayout>
</template>
