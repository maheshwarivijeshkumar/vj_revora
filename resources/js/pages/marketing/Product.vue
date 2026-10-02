<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import DashboardPreview from '@/components/marketing/DashboardPreview.vue';
import FeatureVisual from '@/components/marketing/FeatureVisual.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import Button from '@/components/ui/Button.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

type Visual = 'score' | 'inbox' | 'workflow' | 'attribution';

const chapters: {
    id: string;
    n: string;
    kicker: string;
    title: string;
    body: string;
    detail: { term: string; description: string }[];
    visual?: Visual;
}[] = [
    {
        id: 'capture',
        n: '01',
        kicker: 'Capture',
        title: 'Connect a source once, then stop thinking about it',
        body: 'OAuth connections to the platforms that actually expose lead data, plus your own forms, imports and API. Everything converges on one normalized record before it reaches your team.',
        detail: [
            {
                term: 'Webhook first, polling as backstop',
                description:
                    'Real-time where the provider supports it, with periodic reconciliation so a dropped webhook does not become a lost lead.',
            },
            {
                term: 'Layered deduplication',
                description:
                    'External ID, then normalized email, then phone, then company domain. Duplicates become tombstones pointing at the master. Nothing is destroyed.',
            },
            {
                term: 'Attribution retained',
                description:
                    'UTM values, campaign, ad, form, landing page and referrer stay on the record instead of dying at the first export.',
            },
            {
                term: 'Idempotent ingestion',
                description:
                    'Replayed webhooks are cheap no-ops. A provider retrying delivery cannot create a second lead.',
            },
        ],
    },
    {
        id: 'qualify',
        n: '02',
        kicker: 'Qualify',
        title: 'A score your team will actually believe',
        body: 'Rules and AI together, never opaque. Every score arrives with the reasons that produced it, so a rep can trust it at a glance or override it with context the model did not have.',
        visual: 'score',
        detail: [
            {
                term: 'Configurable rules',
                description:
                    'Points, bands and thresholds are set per workspace. A demo request might be worth 25 to you and 5 to someone else.',
            },
            {
                term: 'Structured AI output',
                description:
                    'Intent, buying stage, budget signal, urgency and recommended action, returned as data rather than prose.',
            },
            {
                term: 'Stored bands',
                description:
                    'The band is written at scoring time, so retuning thresholds later does not silently rewrite the history of every lead you have ever scored.',
            },
        ],
    },
    {
        id: 'engage',
        n: '03',
        kicker: 'Engage',
        title: 'One inbox for WhatsApp, email and SMS',
        body: 'Every conversation in a single thread list, with the lead record, score, owner and open deals beside it. No tab switching to find out who you are talking to.',
        visual: 'inbox',
        detail: [
            {
                term: 'AI suggests, humans approve',
                description:
                    'Drafts appear inline. Nothing sends autonomously unless a workflow explicitly grants it.',
            },
            {
                term: 'Opt-out enforced at dispatch',
                description:
                    'Consent, opt-out and business hours are checked in the send layer, after templating and after automation. There is no path that skips it.',
            },
            {
                term: 'SLA timers',
                description:
                    'First response targets per lead type, with escalation when they are missed and a recorded violation either way.',
            },
        ],
    },
    {
        id: 'automate',
        n: '04',
        kicker: 'Automate',
        title: 'Automation you decide how far to trust',
        body: 'The same engine runs in three modes. Start with recommendations while you watch it, move to approvals when you mostly agree, and switch on autonomous execution only where the stakes are low enough.',
        visual: 'workflow',
        detail: [
            {
                term: 'Manual, approval, autonomous',
                description:
                    'Set per workflow. An approval-mode action is persisted complete and waits for a person, so nothing is recomputed on approval.',
            },
            {
                term: 'Guardrails, not good intentions',
                description:
                    'Quiet hours, rate limits, retry policy and a workspace kill switch that halts every autonomous action immediately.',
            },
            {
                term: 'Idempotent runs',
                description:
                    'A retried job cannot double-send or double-charge. Every run is logged and auditable.',
            },
        ],
    },
    {
        id: 'attribute',
        n: '05',
        kicker: 'Attribute',
        title: 'Revenue traced back to the ad that caused it',
        body: 'First touch, last touch and conversion source held end to end, computed from an append-only event log rather than by querying live CRM tables at report time.',
        visual: 'attribution',
        detail: [
            {
                term: 'The metrics that decide spend',
                description:
                    'Leads by source, qualified rate by source, cost per lead, cost per qualified lead and revenue by campaign.',
            },
            {
                term: 'Dashboards you assemble',
                description:
                    'Widgets from a catalog, dragged into a layout you save. Role-based defaults, not a fixed screen someone else designed.',
            },
            {
                term: 'Scheduled and permission-aware',
                description:
                    'Reports deliver on a schedule, and exports respect the same permissions the UI does.',
            },
        ],
    },
];
</script>

<template>
    <Head>
        <title>Product — {{ brand.name }}</title>
        <meta
            name="description"
            content="Capture, qualify, engage, automate and attribute. The five stages a lead travels, and what happens at each one."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Product"
            title="Five stages, one record"
            lede="A lead is captured once and enriched in place. Scoring, conversations, automation and attribution all read and write the same row, which is why nothing has to be reconciled afterwards."
        >
            <template #actions>
                <Link href="/contact">
                    <Button variant="brand" size="lg">
                        Book a demo
                        <ArrowRight class="size-4" />
                    </Button>
                </Link>
                <Link href="/integrations">
                    <Button variant="secondary" size="lg"
                        >See integrations</Button
                    >
                </Link>
            </template>
        </PageHero>

        <!-- Overview shot, full width so it reads as the product rather than a
             thumbnail wedged into a column. -->
        <section class="border-b border-border bg-surface-alt">
            <div class="mx-auto max-w-[72rem] px-5 py-12 lg:px-8 lg:py-16">
                <DashboardPreview />
                <p class="mt-3 text-center font-mono text-[0.75rem] text-soft">
                    Product preview. Figures illustrative.
                </p>
            </div>
        </section>

        <!-- Chapters. Each one is a long-form entry rather than a card, and the
             sticky index keeps your place on a page this tall. -->
        <div class="mx-auto max-w-[78rem] px-5 lg:px-8">
            <section
                v-for="(chapter, i) in chapters"
                :id="chapter.id"
                :key="chapter.id"
                class="scroll-mt-24 border-b border-border py-16 last:border-0 lg:py-24"
            >
                <div class="grid gap-10 lg:grid-cols-[14rem_1fr] lg:gap-16">
                    <div class="lg:sticky lg:top-28 lg:self-start">
                        <div class="flex items-baseline gap-3">
                            <span
                                class="font-mono text-[0.8rem] text-soft tabular-nums"
                            >
                                {{ chapter.n }}
                            </span>
                            <span
                                class="text-[0.72rem] font-semibold tracking-[0.13em] text-muted uppercase"
                            >
                                {{ chapter.kicker }}
                            </span>
                        </div>
                        <div
                            class="mt-4 h-px w-full bg-border lg:w-12"
                            aria-hidden="true"
                        />
                    </div>

                    <div>
                        <h2
                            class="max-w-[20ch] font-display text-[1.75rem] leading-[1.12] font-bold tracking-[-0.03em] text-strong sm:text-[2.15rem]"
                        >
                            {{ chapter.title }}
                        </h2>
                        <p
                            class="mt-5 max-w-[60ch] text-[1.02rem] leading-relaxed text-muted"
                        >
                            {{ chapter.body }}
                        </p>

                        <div
                            class="mt-10 grid gap-10"
                            :class="
                                chapter.visual &&
                                'lg:grid-cols-[1.1fr_1fr] lg:items-start'
                            "
                        >
                            <dl
                                class="divide-y divide-border border-t border-border"
                            >
                                <div
                                    v-for="item in chapter.detail"
                                    :key="item.term"
                                    class="py-5"
                                >
                                    <dt
                                        class="text-[0.98rem] font-semibold text-strong"
                                    >
                                        {{ item.term }}
                                    </dt>
                                    <dd
                                        class="mt-1.5 max-w-[62ch] text-[0.93rem] leading-relaxed text-muted"
                                    >
                                        {{ item.description }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="chapter.visual"
                                class="lg:sticky lg:top-28"
                            >
                                <FeatureVisual :variant="chapter.visual" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="border-t border-border bg-surface-alt">
            <div
                class="mx-auto flex max-w-[78rem] flex-wrap items-center justify-between gap-6 px-5 py-14 lg:px-8"
            >
                <div>
                    <h2
                        class="font-display text-[1.6rem] font-bold tracking-[-0.03em] text-strong"
                    >
                        See it against your own lead sources
                    </h2>
                    <p class="mt-2 max-w-[48ch] text-muted">
                        Thirty minutes, your channels, your questions. No slide
                        deck.
                    </p>
                </div>
                <Link href="/contact">
                    <Button variant="brand" size="lg">
                        Book a demo
                        <ArrowRight class="size-4" />
                    </Button>
                </Link>
            </div>
        </section>
    </MarketingLayout>
</template>
