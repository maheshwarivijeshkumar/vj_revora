<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Minus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FaqAccordion from '@/components/marketing/FaqAccordion.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import Button from '@/components/ui/Button.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

type Plan = {
    key: string;
    name: string;
    price: number;
    currency: string;
    interval: string;
    trial_days: number;
    highlights: { label: string; value: string; included: boolean }[];
};

const props = defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    plans: Plan[];
    matrix: {
        group: string;
        rows: {
            label: string;
            values: { value: string; included: boolean }[];
        }[];
    }[];
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

// Second plan by sort order, which is where the intended default sits.
const featured = computed(() => props.plans[1]?.key ?? props.plans[0]?.key);

const showMatrix = ref(false);

function money(plan: Plan): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: plan.currency,
        maximumFractionDigits: 0,
    }).format(plan.price);
}

const faqs = [
    {
        question: 'What counts as a lead?',
        answer: 'One unique person entering your workspace, regardless of how many times they submit a form or message you. Deduplication runs before metering, so a duplicate does not consume quota. Re-engaging someone captured in a previous month does not count again either.',
    },
    {
        question: 'What happens when I hit a limit?',
        answer: 'You see it coming. Usage is metered per workspace with current, remaining and projected figures, and a warning threshold before you run out. Limits are enforced by a central entitlement service, so behaviour is consistent everywhere rather than varying by screen.',
    },
    {
        question: 'Can I change plan mid-cycle?',
        answer: 'Yes. Upgrades apply immediately and are prorated. Downgrades are scheduled for the end of the current period, so you keep what you paid for until it actually expires.',
    },
    {
        question: 'Do AI features cost extra?',
        answer: 'AI credits and tokens are included in your plan allowance and metered per workspace, so you can see exactly what is being spent and on what. Bringing your own provider key is supported if you would rather be billed directly by them.',
    },
    {
        question: 'Is there a contract?',
        answer: 'No. Monthly plans are month to month and cancel from inside the app. Annual billing is available if you would prefer it, and enterprise agreements exist for teams that need procurement, custom terms or dedicated infrastructure.',
    },
    {
        question: 'What do agencies pay?',
        answer: 'Each client workspace is billed on its own plan, and agencies managing several get consolidated billing and volume terms. Talk to us and we will work out something sensible rather than making you buy the top tier per client.',
    },
];
</script>

<template>
    <Head>
        <title>Pricing — {{ brand.name }}</title>
        <meta
            name="description"
            content="Straightforward plans with a free trial on every tier. Full comparison of limits, volume and platform features."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Pricing"
            title="Pick a plan, change it whenever"
            lede="Every tier includes a free trial, the versioned REST API and signed webhooks. Upgrade, downgrade or cancel from inside the app. No sales call required to get started."
        />

        <!-- Plan cards -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8 lg:py-16">
                <div class="grid gap-5 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.key"
                        class="relative flex flex-col rounded-xl border p-7"
                        :class="
                            plan.key === featured
                                ? 'border-primary-500 bg-surface-alt'
                                : 'border-border'
                        "
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <h2
                                class="font-display text-[1.3rem] font-bold tracking-[-0.02em] text-strong"
                            >
                                {{ plan.name }}
                            </h2>
                            <span
                                v-if="plan.key === featured"
                                class="font-mono text-[0.72rem] tracking-tight"
                                :style="{ color: 'var(--brand-primary)' }"
                            >
                                most chosen
                            </span>
                        </div>

                        <p class="mt-5 flex items-baseline gap-1.5">
                            <span
                                class="font-display text-[2.6rem] leading-none font-bold tracking-[-0.03em] text-strong tabular-nums"
                            >
                                {{ money(plan) }}
                            </span>
                            <span class="text-[0.9rem] text-muted">
                                /{{
                                    plan.interval === 'annual'
                                        ? 'year'
                                        : 'month'
                                }}
                            </span>
                        </p>

                        <p
                            v-if="plan.trial_days > 0"
                            class="mt-2 text-[0.85rem] text-muted"
                        >
                            {{ plan.trial_days }}-day trial, no card
                        </p>

                        <Link href="/contact" class="mt-6 block">
                            <Button
                                :variant="
                                    plan.key === featured
                                        ? 'brand'
                                        : 'secondary'
                                "
                                size="lg"
                                class="w-full"
                            >
                                Start free trial
                            </Button>
                        </Link>

                        <dl class="mt-7 space-y-3 border-t border-border pt-6">
                            <div
                                v-for="item in plan.highlights"
                                :key="item.label"
                                class="flex items-baseline justify-between gap-3 text-[0.9rem]"
                            >
                                <dt
                                    :class="
                                        item.included
                                            ? 'text-muted'
                                            : 'text-soft'
                                    "
                                >
                                    {{ item.label }}
                                </dt>
                                <dd
                                    class="shrink-0 font-medium tabular-nums"
                                    :class="
                                        item.included
                                            ? 'text-strong'
                                            : 'text-soft'
                                    "
                                >
                                    {{ item.value }}
                                </dd>
                            </div>
                        </dl>
                    </article>
                </div>

                <!-- Enterprise sits outside the grid: it is a conversation, not
                     a fourth card with a hidden price. -->
                <div
                    class="mt-5 flex flex-wrap items-center justify-between gap-5 rounded-xl border border-border bg-surface-alt px-7 py-6"
                >
                    <div>
                        <h2 class="text-[1.1rem] font-semibold text-strong">
                            Enterprise
                        </h2>
                        <p class="mt-1 max-w-[56ch] text-[0.92rem] text-muted">
                            Dedicated infrastructure, data residency, white
                            labelling, custom contracts and onboarding. Priced
                            on what you actually need.
                        </p>
                    </div>
                    <Link href="/contact">
                        <Button variant="secondary" size="lg"
                            >Talk to us</Button
                        >
                    </Link>
                </div>
            </div>
        </section>

        <!-- Full comparison. Collapsed by default: it is long, and most people
             decide from the cards. -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2
                        class="font-display text-[1.6rem] font-bold tracking-[-0.03em] text-strong"
                    >
                        Compare every limit
                    </h2>
                    <button
                        type="button"
                        class="text-[0.92rem] font-medium text-strong underline decoration-border underline-offset-4"
                        :aria-expanded="showMatrix"
                        @click="showMatrix = !showMatrix"
                    >
                        {{
                            showMatrix
                                ? 'Hide comparison'
                                : 'Show full comparison'
                        }}
                    </button>
                </div>

                <div v-show="showMatrix" class="mt-8 overflow-x-auto">
                    <table
                        class="w-full min-w-[42rem] border-collapse text-[0.9rem]"
                    >
                        <thead>
                            <tr class="border-b border-border-strong">
                                <th
                                    class="py-3 pr-4 text-left font-semibold text-strong"
                                >
                                    Feature
                                </th>
                                <th
                                    v-for="plan in plans"
                                    :key="plan.key"
                                    class="px-4 py-3 text-right font-semibold text-strong"
                                >
                                    {{ plan.name }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="group in matrix"
                                :key="group.group"
                            >
                                <tr>
                                    <th
                                        :colspan="plans.length + 1"
                                        class="pt-7 pb-2 text-left text-[0.72rem] font-semibold tracking-[0.13em] text-soft uppercase"
                                    >
                                        {{ group.group }}
                                    </th>
                                </tr>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.label"
                                    class="border-b border-border"
                                >
                                    <td class="py-2.5 pr-4 text-muted">
                                        {{ row.label }}
                                    </td>
                                    <td
                                        v-for="(cell, i) in row.values"
                                        :key="i"
                                        class="px-4 py-2.5 text-right tabular-nums"
                                        :class="
                                            cell.included
                                                ? 'text-strong'
                                                : 'text-soft'
                                        "
                                    >
                                        <span
                                            v-if="cell.value === 'Included'"
                                            class="inline-flex"
                                        >
                                            <Check
                                                class="size-4 text-primary-600"
                                                aria-hidden="true"
                                            />
                                            <span class="sr-only"
                                                >Included</span
                                            >
                                        </span>
                                        <span
                                            v-else-if="cell.value === '—'"
                                            class="inline-flex"
                                        >
                                            <Minus
                                                class="size-4 text-soft"
                                                aria-hidden="true"
                                            />
                                            <span class="sr-only"
                                                >Not included</span
                                            >
                                        </span>
                                        <span v-else>{{ cell.value }}</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-20">
                <div class="grid gap-10 lg:grid-cols-[18rem_1fr] lg:gap-16">
                    <div>
                        <h2
                            class="font-display text-[1.6rem] leading-tight font-bold tracking-[-0.03em] text-strong"
                        >
                            Questions about billing
                        </h2>
                        <p
                            class="mt-3 text-[0.93rem] leading-relaxed text-muted"
                        >
                            Anything not covered here,
                            <Link
                                href="/contact"
                                class="font-medium text-strong underline decoration-border underline-offset-4"
                            >
                                just ask </Link
                            >.
                        </p>
                    </div>
                    <FaqAccordion :items="faqs" />
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
