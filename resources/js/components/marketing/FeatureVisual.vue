<script setup lang="ts">
import { Check, Sparkles } from 'lucide-vue-next';

/**
 * Abstract visual for each feature row.
 *
 * Built from the same design tokens as the product rather than from stock
 * imagery or a screenshot, so each one re-themes with the brand, works in both
 * colour schemes, stays truthful as modules ship, and costs a couple of KB.
 *
 * Replace a variant with a real screenshot once its module exists.
 */
defineProps<{ variant: 'score' | 'inbox' | 'workflow' | 'attribution' }>();

const reasons = [
    { label: 'Demo requested', points: '+25' },
    { label: 'High purchase intent', points: '+20' },
    { label: 'Budget provided', points: '+15' },
    { label: 'Replied within 5 min', points: '+12' },
];

const messages = [
    {
        channel: 'WhatsApp',
        name: 'Amara Okafor',
        preview: 'Can you send pricing for 20 seats?',
        tone: 'emerald',
        unread: true,
    },
    {
        channel: 'Email',
        name: 'Daniel Reyes',
        preview: 'Following up on the demo call…',
        tone: 'blue',
        unread: false,
    },
    {
        channel: 'SMS',
        name: 'Priya Nair',
        preview: 'Yes, Thursday 2pm works.',
        tone: 'violet',
        unread: true,
    },
];

const CHANNEL_TONES: Record<string, string> = {
    emerald:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    violet: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
};

const sources = [
    { label: 'Meta Lead Ads', leads: 1284, revenue: '$48.2k', pct: 100 },
    { label: 'LinkedIn', leads: 742, revenue: '$31.6k', pct: 66 },
    { label: 'Google Ads', leads: 519, revenue: '$19.4k', pct: 41 },
    { label: 'Website forms', leads: 302, revenue: '$9.1k', pct: 24 },
];
</script>

<template>
    <div
        class="rounded-[var(--radius-card)] border border-border bg-surface p-5 shadow-card"
        role="img"
        :aria-label="`Illustration of the ${variant} feature`"
    >
        <!-- Explainable lead score -->
        <div v-if="variant === 'score'">
            <div class="flex items-center justify-between">
                <span class="font-medium text-body text-strong"
                    >Lead score</span
                >
                <span
                    class="rounded-[var(--radius-pill)] bg-emerald-50 px-2.5 py-0.5 text-caption font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    Very High
                </span>
            </div>

            <div class="mt-4 flex items-end gap-3">
                <span
                    class="font-display text-[3rem] leading-none font-bold tabular-nums"
                    :style="{ color: 'var(--brand-primary)' }"
                >
                    82
                </span>
                <span class="pb-1.5 text-small text-muted">/ 100</span>
            </div>

            <div
                class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken"
            >
                <div
                    class="h-full rounded-full"
                    style="width: 82%"
                    :style="{
                        width: '82%',
                        background: 'var(--brand-gradient)',
                    }"
                />
            </div>

            <p
                class="mt-5 flex items-center gap-1.5 text-caption font-semibold tracking-wide text-muted uppercase"
            >
                <Sparkles class="size-3.5" aria-hidden="true" />
                Why
            </p>
            <ul class="mt-2.5 space-y-2">
                <li
                    v-for="reason in reasons"
                    :key="reason.label"
                    class="flex items-center justify-between gap-3 rounded-[var(--radius-control)] bg-surface-alt px-3 py-2 text-small"
                >
                    <span class="truncate text-body">{{ reason.label }}</span>
                    <span
                        class="shrink-0 font-semibold text-primary-600 tabular-nums"
                    >
                        {{ reason.points }}
                    </span>
                </li>
            </ul>
        </div>

        <!-- Omnichannel inbox -->
        <div v-else-if="variant === 'inbox'">
            <div class="flex items-center gap-2 border-b border-border pb-3">
                <span
                    v-for="tab in ['All', 'WhatsApp', 'Email', 'SMS']"
                    :key="tab"
                    class="rounded-[var(--radius-control)] px-2.5 py-1 text-caption font-medium"
                    :class="
                        tab === 'All'
                            ? 'text-primary-700 dark:text-primary-200'
                            : 'text-muted'
                    "
                    :style="
                        tab === 'All'
                            ? { background: 'var(--brand-soft)' }
                            : undefined
                    "
                >
                    {{ tab }}
                </span>
            </div>

            <ul class="divide-y divide-border-soft">
                <li
                    v-for="message in messages"
                    :key="message.name"
                    class="flex items-start gap-3 py-3.5"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-caption font-semibold text-primary-700 dark:text-primary-200"
                        :style="{ background: 'var(--brand-soft)' }"
                        aria-hidden="true"
                    >
                        {{
                            message.name
                                .split(' ')
                                .map((p) => p[0])
                                .join('')
                        }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="truncate font-medium text-body text-strong"
                            >
                                {{ message.name }}
                            </span>
                            <span
                                class="shrink-0 rounded-[var(--radius-pill)] px-1.5 py-0.5 text-[0.625rem] font-medium"
                                :class="CHANNEL_TONES[message.tone]"
                            >
                                {{ message.channel }}
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-small text-muted">
                            {{ message.preview }}
                        </p>
                    </div>
                    <span
                        v-if="message.unread"
                        class="mt-2 size-2 shrink-0 rounded-full"
                        :style="{ background: 'var(--brand-primary)' }"
                        aria-hidden="true"
                    />
                </li>
            </ul>

            <div
                class="mt-1 flex items-center gap-2 rounded-[var(--radius-control)] border border-dashed border-border-strong px-3 py-2.5"
            >
                <Sparkles
                    class="size-3.5 shrink-0 text-primary-600"
                    aria-hidden="true"
                />
                <span class="truncate text-small text-muted">
                    AI suggested: "Happy to help — here's pricing for 20 seats…"
                </span>
            </div>
        </div>

        <!-- Workflow builder -->
        <div v-else-if="variant === 'workflow'">
            <div class="flex items-center justify-between">
                <span class="font-medium text-body text-strong"
                    >New lead → qualified</span
                >
                <span
                    class="rounded-[var(--radius-pill)] bg-amber-50 px-2.5 py-0.5 text-caption font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                >
                    Approval mode
                </span>
            </div>

            <ol class="mt-5 space-y-0">
                <li
                    v-for="(node, i) in [
                        {
                            kind: 'Trigger',
                            label: 'Lead created',
                            tone: 'brand',
                        },
                        {
                            kind: 'Condition',
                            label: 'Score ≥ 70 and country = UAE',
                            tone: 'neutral',
                        },
                        {
                            kind: 'Action',
                            label: 'Assign to UAE Sales Team',
                            tone: 'neutral',
                        },
                        {
                            kind: 'Action',
                            label: 'Send WhatsApp template',
                            tone: 'neutral',
                        },
                    ]"
                    :key="node.label"
                >
                    <div
                        class="rounded-[var(--radius-control)] border px-3.5 py-2.5"
                        :class="
                            node.tone === 'brand'
                                ? ''
                                : 'border-border bg-surface-alt'
                        "
                        :style="
                            node.tone === 'brand'
                                ? {
                                      borderColor: 'var(--brand-primary)',
                                      background: 'var(--brand-soft)',
                                  }
                                : undefined
                        "
                    >
                        <span
                            class="block text-[0.625rem] font-semibold tracking-wide text-muted uppercase"
                        >
                            {{ node.kind }}
                        </span>
                        <span
                            class="mt-0.5 block text-small font-medium text-strong"
                        >
                            {{ node.label }}
                        </span>
                    </div>
                    <div
                        v-if="i < 3"
                        class="mx-auto"
                        :style="{
                            width: '2px',
                            height: '16px',
                            background: 'var(--border-strong)',
                        }"
                        aria-hidden="true"
                    />
                </li>
            </ol>

            <div class="mt-4 flex items-center gap-2 text-small text-muted">
                <Check
                    class="size-4 shrink-0 text-primary-600"
                    aria-hidden="true"
                />
                Waiting for approval · 3 runs queued
            </div>
        </div>

        <!-- Revenue attribution -->
        <div v-else>
            <div class="flex items-center justify-between">
                <span class="font-medium text-body text-strong"
                    >Revenue by source</span
                >
                <span class="text-caption text-muted">Last 90 days</span>
            </div>

            <ul class="mt-5 space-y-4">
                <li v-for="source in sources" :key="source.label">
                    <div
                        class="flex items-center justify-between gap-3 text-small"
                    >
                        <span class="truncate text-body">{{
                            source.label
                        }}</span>
                        <span
                            class="shrink-0 font-semibold text-strong tabular-nums"
                        >
                            {{ source.revenue }}
                        </span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2.5">
                        <div
                            class="h-2 flex-1 overflow-hidden rounded-full bg-surface-sunken"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${source.pct}%`,
                                    background: 'var(--brand-gradient)',
                                }"
                            />
                        </div>
                        <span
                            class="w-12 shrink-0 text-right text-caption text-muted tabular-nums"
                        >
                            {{ source.leads }}
                        </span>
                    </div>
                </li>
            </ul>

            <div
                class="mt-5 grid grid-cols-2 gap-3 border-t border-border pt-4 text-center"
            >
                <div>
                    <div class="text-h3 font-bold text-strong tabular-nums">
                        $18.40
                    </div>
                    <div class="text-caption text-muted">Cost per lead</div>
                </div>
                <div>
                    <div class="text-h3 font-bold text-strong tabular-nums">
                        4.2×
                    </div>
                    <div class="text-caption text-muted">
                        Return on ad spend
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
