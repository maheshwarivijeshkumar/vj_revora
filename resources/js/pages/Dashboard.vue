<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Handshake,
    Landmark,
    ListTodo,
    PlugZap,
    TrendingUp,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component } from 'vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import Card from '@/components/ui/Card.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const { timeZone } = useClock();
type Metric = { key: string; label: string; value: number; href: string };

type Stage = {
    key: string;
    name: string;
    count: number;
    won: boolean;
    lost: boolean;
};

type Day = { date: string; label: string; count: number };

type LeadRow = {
    id: number;
    name: string;
    company: string | null;
    status: string;
    tone: string;
    href: string;
    at: string | null;
};

type TaskRow = {
    id: number;
    title: string;
    status: string;
    due_at: string | null;
    href: string | null;
};

type Person = { id: number; name: string; open_leads: number };

type Connection = {
    key: string;
    label: string;
    state: 'connected' | 'sign_in' | 'setup';
    detail: string;
    href: string;
};

const props = defineProps<{
    metrics: Metric[];
    pipeline: { name: string | null; href: string; stages: Stage[] };
    activity: Day[];
    leads: LeadRow[];
    tasks: TaskRow[];
    people: Person[];
    connections: Connection[];
    work: {
        metrics: { label: string; value: string; href: string }[];
        schedule: { id: number; title: string; at: string; crew: string | null; location: string | null }[];
        attention: { label: string; href: string }[];
    };
}>();

const page = usePage();
const firstName = computed(() => {
    const name = page.props.auth?.user?.name ?? '';
    return name.split(' ')[0] || 'there';
});

const icons: Record<string, Component> = {
    leads: UsersRound,
    qualified: TrendingUp,
    deals: Handshake,
    tenders: Landmark,
};

const peak = computed(() => Math.max(1, ...props.activity.map((day) => day.count)));

function when(value: string | null): string {
    if (!value) {
        return 'No date';
    }

    return new Date(value).toLocaleDateString(undefined, { timeZone: timeZone.value,
        day: 'numeric',
        month: 'short',
    });
}

function stateLabel(state: Connection['state']): string {
    if (state === 'connected') {
        return 'Connected';
    }

    return state === 'sign_in' ? 'Sign in' : 'Set up';
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard" :breadcrumbs="[{ label: 'Dashboard' }]">
        <div class="space-y-4">
            <div>
                <h2 class="text-h3 font-semibold text-strong">
                    Hello {{ firstName }}
                </h2>
                <p class="mt-1 max-w-2xl text-[0.9rem] text-muted">
                    Open work, the sales pipeline, and the accounts that bring
                    leads in.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="metric in metrics"
                    :key="metric.key"
                    :href="metric.href"
                    class="block rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                    <MetricCard
                        :label="metric.label"
                        :value="metric.value"
                        :icon="icons[metric.key] ?? UsersRound"
                    />
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="item in work.metrics"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-xl border border-border bg-surface p-4 hover:border-primary-600"
                >
                    <p class="text-[0.82rem] text-muted">{{ item.label }}</p>
                    <p class="mt-1 text-h3 font-semibold tabular-nums text-strong">{{ item.value }}</p>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-[0.98rem] font-semibold text-strong">Today's schedule</h3>
                        <Link href="/schedule" class="text-[0.85rem] font-medium text-primary-600 hover:underline">Open schedule</Link>
                    </div>
                    <p v-if="work.schedule.length === 0" class="mt-3 text-[0.88rem] text-muted">
                        Nothing booked for today.
                    </p>
                    <ul v-else class="mt-3 space-y-2">
                        <li v-for="visit in work.schedule" :key="visit.id" class="text-[0.88rem]">
                            <span class="font-medium text-strong">{{ visit.at }}</span>
                            {{ visit.title }}
                            <span v-if="visit.crew" class="text-muted"> · {{ visit.crew }}</span>
                            <span v-if="visit.location" class="text-muted"> · {{ visit.location }}</span>
                        </li>
                    </ul>
                </Card>
                <Card>
                    <h3 class="text-[0.98rem] font-semibold text-strong">Needs attention</h3>
                    <p v-if="work.attention.length === 0" class="mt-3 text-[0.88rem] text-muted">
                        No estimates waiting, and no invoices past their due date.
                    </p>
                    <ul v-else class="mt-3 space-y-2">
                        <li v-for="item in work.attention" :key="item.label">
                            <Link :href="item.href" class="text-[0.88rem] text-primary-600 hover:underline">{{ item.label }}</Link>
                        </li>
                    </ul>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-[0.98rem] font-semibold text-strong">
                                {{ pipeline.name ?? 'Sales pipeline' }}
                            </h3>
                            <p class="mt-0.5 text-[0.82rem] text-muted">
                                Every stage a deal moves through.
                            </p>
                        </div>
                        <Link
                            :href="pipeline.href"
                            class="text-[0.85rem] font-medium text-primary-600 hover:underline"
                        >
                            Open board
                        </Link>
                    </div>

                    <p
                        v-if="pipeline.stages.length === 0"
                        class="mt-4 text-[0.88rem] text-muted"
                    >
                        No pipeline yet. The sales board creates one when the
                        first deal is added.
                    </p>

                    <div v-else class="mt-4 flex gap-2 overflow-x-auto pb-1">
                        <Link
                            v-for="stage in pipeline.stages"
                            :key="stage.key"
                            :href="pipeline.href"
                            class="min-w-28 flex-1 rounded-xl border border-border bg-surface-alt px-3 py-3 hover:border-border-strong"
                        >
                            <p class="truncate text-[0.78rem] text-muted">
                                {{ stage.name }}
                            </p>
                            <p
                                class="mt-1 text-h3 font-semibold tabular-nums text-strong"
                            >
                                {{ stage.count }}
                            </p>
                        </Link>
                    </div>
                </Card>

                <Card>
                    <h3 class="text-[0.98rem] font-semibold text-strong">
                        Leads this week
                    </h3>
                    <p class="mt-0.5 text-[0.82rem] text-muted">
                        New leads by day.
                    </p>
                    <div class="mt-4 flex h-36 items-end gap-2">
                        <div
                            v-for="day in activity"
                            :key="day.date"
                            class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                        >
                            <span class="text-[0.72rem] tabular-nums text-muted">
                                {{ day.count }}
                            </span>
                            <div
                                class="w-full rounded-t-md bg-primary-500"
                                :style="{
                                    height: `${Math.max(8, (day.count / peak) * 100)}%`,
                                }"
                            />
                            <span class="text-[0.72rem] text-muted">
                                {{ day.label }}
                            </span>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-2" :padded="false">
                    <div class="flex items-center justify-between px-5 pt-5">
                        <h3 class="text-[0.98rem] font-semibold text-strong">
                            Latest leads
                        </h3>
                        <Link
                            href="/leads"
                            class="text-[0.85rem] font-medium text-primary-600 hover:underline"
                        >
                            All leads
                        </Link>
                    </div>
                    <p
                        v-if="leads.length === 0"
                        class="px-5 py-8 text-[0.88rem] text-muted"
                    >
                        No leads yet.
                        <Link href="/leads" class="font-medium text-primary-600 hover:underline">
                            Add the first one
                        </Link>
                        or connect an ad account.
                    </p>
                    <table v-else class="mt-3 w-full text-left text-body">
                        <thead class="text-[0.78rem] text-muted">
                            <tr>
                                <th class="px-5 py-2 font-medium">Lead</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-5 py-2 font-medium">Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="lead in leads"
                                :key="lead.id"
                                class="border-t border-border"
                            >
                                <td class="px-5 py-3">
                                    <Link
                                        :href="lead.href"
                                        class="font-medium text-strong hover:underline"
                                    >
                                        {{ lead.name }}
                                    </Link>
                                    <p
                                        v-if="lead.company"
                                        class="text-[0.8rem] text-muted"
                                    >
                                        {{ lead.company }}
                                    </p>
                                </td>
                                <td class="px-3 py-3 text-[0.85rem] text-body">
                                    {{ lead.status }}
                                </td>
                                <td class="px-5 py-3 text-[0.85rem] text-muted">
                                    {{ when(lead.at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>

                <Card>
                    <div class="flex items-center gap-2">
                        <PlugZap class="size-4 text-primary-600" />
                        <h3 class="text-[0.98rem] font-semibold text-strong">
                            Connected accounts
                        </h3>
                    </div>
                    <ul class="mt-3 divide-y divide-border">
                        <li v-for="connection in connections" :key="connection.key">
                            <Link
                                :href="connection.href"
                                class="flex items-start justify-between gap-3 py-3"
                            >
                                <span class="min-w-0">
                                    <span class="block text-[0.9rem] font-medium text-strong">
                                        {{ connection.label }}
                                    </span>
                                    <span class="mt-0.5 block text-[0.8rem] text-muted">
                                        {{ connection.detail }}
                                    </span>
                                </span>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[0.75rem] font-medium"
                                    :class="
                                        connection.state === 'connected'
                                            ? 'bg-primary-50 text-primary-700'
                                            : 'bg-surface-alt text-muted'
                                    "
                                >
                                    {{ stateLabel(connection.state) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <div class="flex items-center gap-2">
                        <ListTodo class="size-4 text-primary-600" />
                        <h3 class="text-[0.98rem] font-semibold text-strong">
                            Open tasks
                        </h3>
                    </div>
                    <p
                        v-if="tasks.length === 0"
                        class="mt-3 text-[0.88rem] text-muted"
                    >
                        Nothing due. Tasks are added from a lead or a deal.
                    </p>
                    <ul v-else class="mt-3 divide-y divide-border">
                        <li
                            v-for="task in tasks"
                            :key="task.id"
                            class="flex items-center justify-between gap-3 py-2.5"
                        >
                            <component
                                :is="task.href ? Link : 'span'"
                                :href="task.href ?? undefined"
                                class="min-w-0 truncate text-[0.9rem] text-strong"
                                :class="task.href ? 'hover:underline' : ''"
                            >
                                {{ task.title }}
                            </component>
                            <span class="shrink-0 text-[0.8rem] text-muted">
                                {{ task.status }}
                                <template v-if="task.due_at">
                                    · {{ when(task.due_at) }}
                                </template>
                            </span>
                        </li>
                    </ul>
                </Card>

                <Card>
                    <div class="flex items-center gap-2">
                        <UsersRound class="size-4 text-primary-600" />
                        <h3 class="text-[0.98rem] font-semibold text-strong">
                            Who owns the leads
                        </h3>
                    </div>
                    <p
                        v-if="people.length === 0"
                        class="mt-3 text-[0.88rem] text-muted"
                    >
                        Open leads have no owner yet. Assign one from the lead.
                    </p>
                    <ol v-else class="mt-3 space-y-2">
                        <li
                            v-for="(person, index) in people"
                            :key="person.id"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="min-w-0 truncate text-[0.9rem] text-strong">
                                <span class="mr-2 text-[0.8rem] text-muted">
                                    {{ index + 1 }}
                                </span>
                                {{ person.name }}
                            </span>
                            <span class="text-[0.82rem] text-muted">
                                {{ person.open_leads }} open
                            </span>
                        </li>
                    </ol>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
