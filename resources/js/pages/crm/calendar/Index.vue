<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type CalendarEvent = {
    date: string;
    kind: 'lead' | 'deal';
    title: string;
    subtitle: string;
    href: string;
};

type Day = {
    date: string;
    day: number;
    in_month: boolean;
    events: CalendarEvent[];
};

const props = defineProps<{
    month: string;
    label: string;
    previous: string;
    next: string;
    today: string;
    days: Day[];
}>();

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const hasEvents = computed(() => props.days.some((day) => day.events.length > 0));
</script>

<template>
    <Head title="Calendar" />

    <AppLayout title="Calendar" :breadcrumbs="[{ label: 'Calendar' }]">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-3xl text-[0.88rem] text-muted">
                    Approved leads (qualified, appointment, proposal or won)
                    appear on the day they came in. Closed client deals appear
                    on the day they were won.
                </p>
                <div class="flex items-center gap-2">
                    <Link
                        :href="`/calendar?month=${previous}`"
                        class="inline-flex size-9 items-center justify-center rounded-[var(--radius-control)] border border-border text-strong hover:bg-surface-alt"
                        :aria-label="`Previous month`"
                    >
                        <ChevronLeft class="size-4" />
                    </Link>
                    <p class="min-w-32 text-center text-body font-medium text-strong">
                        {{ label }}
                    </p>
                    <Link
                        :href="`/calendar?month=${next}`"
                        class="inline-flex size-9 items-center justify-center rounded-[var(--radius-control)] border border-border text-strong hover:bg-surface-alt"
                        aria-label="Next month"
                    >
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-small text-muted">
                <span class="inline-flex items-center gap-2">
                    <span class="size-2 rounded-full bg-primary-600" />
                    Approved lead
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="size-2 rounded-full bg-success" />
                    Closed client
                </span>
            </div>

            <EmptyState
                v-if="!hasEvents"
                title="Nothing approved this month"
                description="Qualified leads and won deals land here. New leads and lost deals do not."
            >
                <template #icon>
                    <CalendarDays class="size-5" />
                </template>
            </EmptyState>

            <div class="overflow-hidden rounded-xl border border-border bg-surface">
                <div class="grid grid-cols-7 border-b border-border text-small text-muted">
                    <div v-for="weekday in weekdays" :key="weekday" class="px-2 py-2 text-center">
                        {{ weekday }}
                    </div>
                </div>
                <div class="grid grid-cols-7">
                    <div
                        v-for="day in days"
                        :key="day.date"
                        class="min-h-24 border-b border-r border-border p-1.5 last:border-r-0"
                        :class="day.date === today ? 'bg-primary-50/60' : ''"
                    >
                        <p
                            class="px-1 text-small"
                            :class="day.in_month ? 'text-strong' : 'text-muted'"
                        >
                            {{ day.day }}
                        </p>
                        <div class="mt-1 space-y-1">
                            <Link
                                v-for="event in day.events"
                                :key="`${event.kind}-${event.href}`"
                                :href="event.href"
                                class="block truncate rounded px-1.5 py-1 text-small leading-tight"
                                :class="
                                    event.kind === 'deal'
                                        ? 'bg-success/10 text-strong'
                                        : 'bg-primary-50 text-primary-700'
                                "
                            >
                                <span class="block truncate font-medium">{{ event.title }}</span>
                                <span class="block truncate text-muted">{{ event.subtitle }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
