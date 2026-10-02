<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    KeyRound,
    Server,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';

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

    return new Date(value).toLocaleString(undefined, {
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
            <!-- Filters -->
            <div class="rounded-xl border border-border bg-surface p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Action
                        </span>
                        <SelectMenu
                            id="audit-action"
                            v-model="action"
                            :options="actionOptions"
                            @update:model-value="apply"
                        />
                    </label>
                    <label class="block">
                        <span
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            Who
                        </span>
                        <SelectMenu
                            id="audit-actor"
                            v-model="actor"
                            :options="actorOptions"
                            @update:model-value="apply"
                        />
                    </label>
                    <div>
                        <label
                            for="audit-from"
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            From
                        </label>
                        <TextInput
                            id="audit-from"
                            v-model="from"
                            type="date"
                            @change="apply"
                        />
                    </div>
                    <div>
                        <label
                            for="audit-to"
                            class="mb-1.5 block text-[0.82rem] font-medium text-strong"
                        >
                            To
                        </label>
                        <TextInput
                            id="audit-to"
                            v-model="to"
                            type="date"
                            @change="apply"
                        />
                    </div>
                </div>

                <div
                    v-if="hasFilters"
                    class="mt-3 flex items-center justify-between"
                >
                    <p class="text-[0.85rem] text-muted">
                        {{ logs.meta.total }}
                        {{ logs.meta.total === 1 ? 'entry' : 'entries' }} match
                    </p>
                    <Button variant="ghost" size="sm" @click="clear">
                        Clear filters
                    </Button>
                </div>
            </div>

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
                <ul class="divide-y divide-border-soft">
                    <li v-for="entry in logs.data" :key="entry.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-alt"
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
                                class="mt-1 size-4 shrink-0 text-soft"
                                aria-hidden="true"
                            />

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        :tone="
                                            entry.is_destructive
                                                ? 'danger'
                                                : 'neutral'
                                        "
                                        :label="entry.action_label"
                                    />
                                    <span
                                        v-if="entry.entity"
                                        class="font-mono text-[0.78rem] text-muted"
                                    >
                                        {{ entry.entity.type }}#{{
                                            entry.entity.id
                                        }}
                                    </span>
                                </span>

                                <span
                                    class="mt-1 flex flex-wrap items-center gap-2 text-[0.85rem] text-muted"
                                >
                                    <component
                                        :is="actorIcon(entry.actor.type)"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ entry.actor.name }}
                                    <span class="text-soft">·</span>
                                    <span class="tabular-nums">{{
                                        formatDate(entry.created_at)
                                    }}</span>
                                    <template v-if="entry.ip">
                                        <span class="text-soft">·</span>
                                        <span
                                            class="font-mono text-[0.78rem]"
                                            >{{ entry.ip }}</span
                                        >
                                    </template>
                                </span>
                            </span>
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

                <!-- Pagination -->
                <div
                    v-if="logs.meta.last_page > 1"
                    class="flex items-center justify-between border-t border-border px-4 py-2.5"
                >
                    <p class="text-[0.85rem] text-muted">
                        Page {{ logs.meta.current_page }} of
                        {{ logs.meta.last_page }} ·
                        {{ logs.meta.total }} entries
                    </p>
                    <div class="flex gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            :disabled="logs.meta.current_page === 1"
                            @click="page(logs.meta.current_page - 1)"
                        >
                            Previous
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            :disabled="
                                logs.meta.current_page === logs.meta.last_page
                            "
                            @click="page(logs.meta.current_page + 1)"
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <p class="text-[0.85rem] text-muted">
                Entries cannot be edited or removed. Passwords, signing secrets
                and API keys are never recorded, only the fact that they
                changed.
            </p>
        </div>
    </AppLayout>
</template>
