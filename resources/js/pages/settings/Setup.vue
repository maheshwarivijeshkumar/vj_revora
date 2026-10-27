<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, CircleDashed } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';

type Step = {
    key: string;
    title: string;
    done: boolean;
    detail: string;
    href: string | null;
    action: string | null;
};

defineProps<{ steps: Step[]; done: number }>();

/** OAuth hand-offs leave the app, so they need a full navigation. */
function isExternal(href: string): boolean {
    return href.includes('/connect');
}
</script>

<template>
    <Head title="Setup" />

    <AppLayout
        title="Setup"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Setup' }]"
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-border bg-surface p-5">
                <p
                    class="text-[0.78rem] font-semibold tracking-wide text-soft uppercase"
                >
                    Progress
                </p>
                <p class="mt-1 text-[1.4rem] font-semibold text-strong">
                    {{ done }} of {{ steps.length }} ready
                </p>
                <div
                    class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunken"
                >
                    <div
                        class="h-full rounded-full bg-primary-600 transition-all"
                        :style="{ width: `${(done / steps.length) * 100}%` }"
                    />
                </div>
                <p class="mt-3 max-w-3xl text-[0.85rem] text-muted">
                    Each check reads what is actually working, not just what is
                    configured. Sign-ins to Facebook, LinkedIn, Google,
                    Microsoft and Upwork are done by you on their own pages;
                    this CRM never asks for those passwords.
                </p>
            </div>

            <ol class="space-y-2">
                <li
                    v-for="(step, index) in steps"
                    :key="step.key"
                    class="flex flex-wrap items-start gap-3 rounded-xl border border-border bg-surface px-4 py-3"
                >
                    <component
                        :is="step.done ? CircleCheck : CircleDashed"
                        class="mt-0.5 size-5 shrink-0"
                        :class="step.done ? 'text-success' : 'text-muted'"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-[0.92rem] font-medium text-strong">
                            {{ index + 1 }}. {{ step.title }}
                            <span class="sr-only">{{
                                step.done ? '(ready)' : '(to do)'
                            }}</span>
                        </p>
                        <p class="mt-0.5 text-[0.84rem] text-muted">
                            {{ step.detail }}
                        </p>
                    </div>
                    <template v-if="step.href && step.action">
                        <a
                            v-if="isExternal(step.href)"
                            :href="step.href"
                            class="shrink-0 text-[0.85rem] font-medium text-primary-600 hover:underline"
                            >{{ step.action }}</a
                        >
                        <Link
                            v-else
                            :href="step.href"
                            class="shrink-0 text-[0.85rem] font-medium text-primary-600 hover:underline"
                            >{{ step.action }}</Link
                        >
                    </template>
                </li>
            </ol>
        </div>
    </AppLayout>
</template>
