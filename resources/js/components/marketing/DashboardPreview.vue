<script setup lang="ts">
import {
    CalendarDays,
    Handshake,
    Inbox,
    LayoutDashboard,
    Search,
    Sparkles,
    TrendingUp,
    UsersRound,
} from 'lucide-vue-next';

/**
 * Abstracted preview of the real dashboard, built from the same design tokens.
 *
 * Deliberately not a screenshot: it stays truthful as the product evolves, it
 * re-themes with the selected brand, it works in both colour schemes, and it
 * costs a few KB instead of a retina PNG. The figures are illustrative and
 * labelled as a product preview rather than presented as customer results
 * (§124 forbids fabricated performance claims).
 */

const metrics = [
    { label: 'Total Leads', value: '2,847', icon: UsersRound, trend: '+12.4%' },
    { label: 'Qualified', value: '1,204', icon: TrendingUp, trend: '+8.1%' },
    { label: 'Open Deals', value: '342', icon: Handshake, trend: '+3.7%' },
    { label: 'Meetings', value: '96', icon: CalendarDays, trend: '+21%' },
];

const rows = [
    {
        name: 'Amara Okafor',
        source: 'Facebook',
        status: 'Qualified',
        tone: 'qualified',
        score: 92,
    },
    {
        name: 'Daniel Reyes',
        source: 'LinkedIn',
        status: 'New',
        tone: 'new',
        score: 81,
    },
    {
        name: 'Priya Nair',
        source: 'WhatsApp',
        status: 'Contacted',
        tone: 'contacted',
        score: 74,
    },
    {
        name: 'Tomas Weber',
        source: 'Website',
        status: 'Proposal',
        tone: 'proposal',
        score: 68,
    },
];

const TONES: Record<string, string> = {
    qualified:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    new: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    contacted: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300',
    proposal:
        'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
};

// Sparkline path over a 200×56 box — a plausible upward trend, not real data.
const trend =
    'M0,48 L25,44 L50,38 L75,40 L100,30 L125,24 L150,26 L175,14 L200,8';
</script>

<template>
    <div
        class="overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface shadow-modal"
        role="img"
        aria-label="Preview of the lead dashboard, showing metric cards, a lead trend chart and a recent leads table"
    >
        <!-- Window chrome -->
        <div
            class="flex items-center gap-2 border-b border-border bg-surface-alt px-4 py-2.5"
        >
            <span class="flex gap-1.5" aria-hidden="true">
                <span class="size-2.5 rounded-full bg-red-400" />
                <span class="size-2.5 rounded-full bg-amber-400" />
                <span class="size-2.5 rounded-full bg-emerald-400" />
            </span>
            <span
                class="mx-auto flex items-center gap-1.5 rounded-[var(--radius-control)] bg-surface px-3 py-1 text-[0.6875rem] text-soft"
            >
                <Search class="size-3" aria-hidden="true" />
                Search leads, contacts, deals…
            </span>
        </div>

        <div class="flex">
            <!-- Sidebar -->
            <div
                class="hidden w-40 shrink-0 space-y-1 border-r border-border bg-sidebar p-3 sm:block"
            >
                <div
                    v-for="(item, i) in [
                        { label: 'Dashboard', icon: LayoutDashboard },
                        { label: 'Leads', icon: UsersRound },
                        { label: 'Inbox', icon: Inbox },
                        { label: 'Deals', icon: Handshake },
                        { label: 'AI', icon: Sparkles },
                    ]"
                    :key="item.label"
                    class="flex items-center gap-2.5 rounded-[var(--radius-control)] px-2.5 py-2 text-[0.75rem]"
                    :class="
                        i === 0
                            ? 'font-medium text-primary-700 dark:text-primary-200'
                            : 'text-muted'
                    "
                    :style="
                        i === 0
                            ? { background: 'var(--brand-soft)' }
                            : undefined
                    "
                >
                    <component
                        :is="item.icon"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </div>
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1 space-y-3 bg-page p-3 sm:p-4">
                <!-- Metric cards -->
                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                    <div
                        v-for="metric in metrics"
                        :key="metric.label"
                        class="rounded-[var(--radius-control)] border border-border bg-surface p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="truncate text-[0.6875rem] text-muted">
                                {{ metric.label }}
                            </span>
                            <component
                                :is="metric.icon"
                                class="size-3.5 shrink-0 text-primary-600"
                                aria-hidden="true"
                            />
                        </div>
                        <div
                            class="mt-1 text-[1.125rem] font-bold text-strong tabular-nums"
                        >
                            {{ metric.value }}
                        </div>
                        <div
                            class="text-[0.625rem] font-medium text-emerald-600"
                        >
                            {{ metric.trend }}
                        </div>
                    </div>
                </div>

                <!-- Trend chart -->
                <div
                    class="rounded-[var(--radius-control)] border border-border bg-surface p-3"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-[0.75rem] font-medium text-strong"
                            >Lead trend</span
                        >
                        <span class="text-[0.625rem] text-muted"
                            >Last 30 days</span
                        >
                    </div>
                    <svg
                        viewBox="0 0 200 56"
                        class="mt-2 h-14 w-full"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <defs>
                            <linearGradient
                                id="dp-fill"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0"
                                    :stop-color="'var(--brand-primary)'"
                                    stop-opacity="0.28"
                                />
                                <stop
                                    offset="1"
                                    :stop-color="'var(--brand-primary)'"
                                    stop-opacity="0"
                                />
                            </linearGradient>
                        </defs>
                        <path
                            :d="`${trend} L200,56 L0,56 Z`"
                            fill="url(#dp-fill)"
                        />
                        <path
                            :d="trend"
                            fill="none"
                            :stroke="'var(--brand-primary)'"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>

                <!-- Recent leads -->
                <div
                    class="overflow-hidden rounded-[var(--radius-control)] border border-border bg-surface"
                >
                    <div
                        class="flex items-center justify-between border-b border-border px-3 py-2"
                    >
                        <span class="text-[0.75rem] font-medium text-strong"
                            >Recent leads</span
                        >
                        <span class="text-[0.625rem] text-muted"
                            >1–4 of 2,847</span
                        >
                    </div>
                    <table class="w-full">
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.name"
                                class="border-b border-border-soft last:border-0"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex size-5 shrink-0 items-center justify-center rounded-full text-[0.5625rem] font-semibold text-primary-700 dark:text-primary-200"
                                            :style="{
                                                background: 'var(--brand-soft)',
                                            }"
                                            aria-hidden="true"
                                        >
                                            {{
                                                row.name
                                                    .split(' ')
                                                    .map((p) => p[0])
                                                    .join('')
                                            }}
                                        </span>
                                        <span
                                            class="truncate text-[0.6875rem] text-strong"
                                        >
                                            {{ row.name }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="hidden px-3 py-2 text-[0.6875rem] text-muted sm:table-cell"
                                >
                                    {{ row.source }}
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-[var(--radius-pill)] px-2 py-0.5 text-[0.5625rem] font-medium"
                                        :class="TONES[row.tone]"
                                    >
                                        {{ row.status }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2 text-right text-[0.6875rem] font-semibold text-strong tabular-nums"
                                >
                                    {{ row.score }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
