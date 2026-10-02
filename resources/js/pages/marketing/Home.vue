<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight, Check } from 'lucide-vue-next';
import DashboardPreview from '@/components/marketing/DashboardPreview.vue';
import FeatureVisual from '@/components/marketing/FeatureVisual.vue';
import SectionLabel from '@/components/marketing/SectionLabel.vue';
import WaitlistForm from '@/components/marketing/WaitlistForm.vue';
import Button from '@/components/ui/Button.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

defineProps<{
    brand: {
        key: string;
        name: string;
        tagline: string;
        assets: Record<string, string>;
    };
    plans: {
        key: string;
        name: string;
        price: number;
        currency: string;
        highlights: { label: string; value: string; included: boolean }[];
    }[];
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const sources = [
    'Meta Lead Ads',
    'Instagram',
    'LinkedIn Lead Sync',
    'TikTok',
    'Google Ads',
    'WhatsApp Business',
    'Website forms',
    'REST API',
];

const problems = [
    {
        n: '01',
        title: 'Leads arrive everywhere, live nowhere',
        body: 'Meta forms, LinkedIn, the website, a spreadsheet someone emails round on Friday. No single record, no reliable count, no owner.',
    },
    {
        n: '02',
        title: 'The fastest reply wins, and it is rarely yours',
        body: 'Buying intent decays in minutes. Manual triage across four inboxes turns a hot lead cold before anyone has read it.',
    },
    {
        n: '03',
        title: 'Nobody can prove what actually worked',
        body: 'Spend goes into campaigns, revenue comes out of deals. The line between them is a guess, and attribution dies at the first CSV export.',
    },
];

const pipeline = [
    { step: 'Connect', note: 'OAuth, once' },
    { step: 'Capture', note: 'Webhook or poll' },
    { step: 'Normalize', note: 'One schema' },
    { step: 'Score', note: 'Rules plus AI' },
    { step: 'Assign', note: 'By rule' },
    { step: 'Engage', note: 'Any channel' },
    { step: 'Convert', note: 'Attributed' },
];

const included = [
    'Multi-tenant workspaces with isolated data',
    'Versioned REST API and signed webhooks',
    'Configurable dashboards, not a fixed layout',
    'Granular roles and permissions',
    'Full audit trail on every record',
    'Consent and opt-out enforced per channel',
];
</script>

<template>
    <Head>
        <title>
            {{ brand.name }} — Lead generation, CRM and sales automation
        </title>
        <meta
            name="description"
            :content="`${brand.name} captures leads from authorized channels, qualifies them with explainable AI, and automates follow-up across WhatsApp, email and SMS.`"
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <!-- ─────────────────────────── Hero ─────────────────────────── -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 lg:px-8">
                <div
                    class="grid items-center gap-12 py-16 lg:grid-cols-[minmax(0,1fr)_1.05fr] lg:gap-16 lg:py-24"
                >
                    <div>
                        <p
                            class="font-mono text-[0.78rem] tracking-tight text-muted"
                        >
                            Multi-tenant · AI-native · API-first
                        </p>

                        <h1
                            class="mt-5 font-display text-[2.6rem] leading-[1.03] font-bold tracking-[-0.035em] text-strong sm:text-[3.6rem]"
                        >
                            Every lead on one
                            <span class="relative whitespace-nowrap">
                                record
                                <!-- Hand-drawn underline rather than a highlight
                                     block: quieter, and it survives dark mode. -->
                                <svg
                                    class="absolute bottom-0 left-0 h-2 w-full translate-y-1"
                                    viewBox="0 0 200 10"
                                    preserveAspectRatio="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M2,7 C42,2 86,9 128,4 C158,1 180,6 198,3"
                                        fill="none"
                                        :stroke="'var(--brand-primary)'"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        opacity="0.55"
                                    />
                                </svg>
                            </span>
                            <br />
                            from click to revenue.
                        </h1>

                        <p
                            class="mt-6 max-w-[46ch] text-[1.05rem] leading-relaxed text-muted"
                        >
                            Connect your authorized lead sources once.
                            Everything that arrives is normalized, deduplicated,
                            scored and routed, then worked from a single inbox
                            across WhatsApp, email and SMS.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <Link href="/contact">
                                <Button variant="brand" size="lg">
                                    Book a demo
                                    <ArrowRight class="size-4" />
                                </Button>
                            </Link>
                            <Link href="/product">
                                <Button variant="secondary" size="lg">
                                    See the product
                                </Button>
                            </Link>
                        </div>

                        <p class="mt-5 text-[0.85rem] text-soft">
                            Free trial on every plan. No card required.
                        </p>
                    </div>

                    <!-- Deliberately allowed to run past the container on large
                         screens, so the hero does not read as two tidy boxes. -->
                    <div class="lg:-mr-16 xl:-mr-28">
                        <DashboardPreview />
                    </div>
                </div>
            </div>
        </section>

        <!-- ──────────────────── Sources ribbon ──────────────────── -->
        <section class="border-b border-border bg-surface-alt">
            <div
                class="mx-auto flex max-w-[78rem] flex-col gap-4 px-5 py-6 lg:flex-row lg:items-center lg:gap-10 lg:px-8"
            >
                <p
                    class="shrink-0 text-[0.72rem] font-semibold tracking-[0.13em] text-soft uppercase"
                >
                    Connects to
                </p>
                <ul class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <li
                        v-for="source in sources"
                        :key="source"
                        class="text-[0.88rem] text-muted"
                    >
                        {{ source }}
                    </li>
                </ul>
            </div>
        </section>

        <!-- ─────────────────────── Problem ─────────────────────── -->
        <!-- Deep navy in both themes. One dark band stops the page reading as
             an endless run of white cards. -->
        <section class="bg-[#0b1524] text-slate-300">
            <div class="mx-auto max-w-[78rem] px-5 py-20 lg:px-8 lg:py-28">
                <div class="grid gap-12 lg:grid-cols-[26rem_1fr] lg:gap-20">
                    <div>
                        <div class="flex items-center gap-3">
                            <span
                                class="font-mono text-[0.78rem] text-slate-500"
                                >01</span
                            >
                            <span
                                class="h-px w-8 bg-slate-700"
                                aria-hidden="true"
                            />
                            <span
                                class="text-[0.72rem] font-semibold tracking-[0.13em] text-slate-400 uppercase"
                            >
                                The problem
                            </span>
                        </div>
                        <h2
                            class="mt-5 font-display text-[2rem] leading-[1.1] font-bold tracking-[-0.03em] text-white sm:text-[2.5rem]"
                        >
                            Most teams do not have a lead problem.
                        </h2>
                        <p
                            class="mt-4 max-w-[38ch] leading-relaxed text-slate-400"
                        >
                            They have a lead handling problem. Plenty arrives.
                            What is missing is one place where it is complete,
                            scored, owned and answered before the intent goes
                            cold.
                        </p>
                    </div>

                    <ul class="divide-y divide-slate-800">
                        <li
                            v-for="problem in problems"
                            :key="problem.n"
                            class="grid gap-4 py-7 first:pt-0 last:pb-0 sm:grid-cols-[3rem_1fr] sm:gap-6"
                        >
                            <span
                                class="font-mono text-[0.85rem] text-slate-600"
                            >
                                {{ problem.n }}
                            </span>
                            <div>
                                <h3
                                    class="text-[1.15rem] font-semibold text-white"
                                >
                                    {{ problem.title }}
                                </h3>
                                <p
                                    class="mt-2 max-w-[58ch] leading-relaxed text-slate-400"
                                >
                                    {{ problem.body }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ─────────────────────── Pipeline ─────────────────────── -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-20">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <SectionLabel index="02" label="How it works" />
                        <h2
                            class="mt-5 max-w-[20ch] font-display text-[1.9rem] leading-[1.12] font-bold tracking-[-0.03em] text-strong sm:text-[2.3rem]"
                        >
                            One pipeline, seven steps, no copy-paste
                        </h2>
                    </div>
                    <Link
                        href="/product"
                        class="group flex items-center gap-1.5 text-[0.92rem] font-medium text-strong"
                    >
                        Walk through it
                        <ArrowUpRight
                            class="size-4 transition-transform duration-150 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        />
                    </Link>
                </div>

                <ol
                    class="mt-12 grid grid-cols-2 border-t border-l border-border sm:grid-cols-4 lg:grid-cols-7"
                >
                    <li
                        v-for="(item, i) in pipeline"
                        :key="item.step"
                        class="border-r border-b border-border px-4 py-5"
                    >
                        <span
                            class="font-mono text-[0.75rem] text-soft tabular-nums"
                        >
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                        <span
                            class="mt-2 block text-[1rem] font-semibold text-strong"
                        >
                            {{ item.step }}
                        </span>
                        <span class="mt-0.5 block text-[0.82rem] text-muted">
                            {{ item.note }}
                        </span>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ──────────────────────── Bento ──────────────────────── -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-24">
                <SectionLabel index="03" label="Product" />
                <div
                    class="mt-5 flex flex-wrap items-end justify-between gap-6"
                >
                    <h2
                        class="max-w-[22ch] font-display text-[1.9rem] leading-[1.12] font-bold tracking-[-0.03em] text-strong sm:text-[2.3rem]"
                    >
                        The intelligence sits on the record, not beside it
                    </h2>
                    <p
                        class="max-w-[38ch] text-[0.95rem] leading-relaxed text-muted"
                    >
                        Scoring, conversations, automation and attribution all
                        read and write the same lead. Nothing has to be
                        reconciled later.
                    </p>
                </div>

                <!-- Uneven cells on purpose. A uniform grid of identical cards is
                     the fastest way to make a page look generated. -->
                <div class="mt-12 grid gap-4 lg:grid-cols-3">
                    <article
                        class="flex flex-col justify-between rounded-xl border border-border bg-surface-alt p-7 lg:col-span-2 lg:row-span-2"
                    >
                        <div>
                            <h3
                                class="font-display text-[1.35rem] font-bold tracking-[-0.02em] text-strong"
                            >
                                Scoring that shows its working
                            </h3>
                            <p
                                class="mt-3 max-w-[50ch] leading-relaxed text-muted"
                            >
                                Rules and AI combined, and always explainable.
                                Every score arrives with the reasons behind it,
                                so your team can trust it or correct it.
                                Thresholds and bands are yours to set.
                            </p>
                        </div>
                        <div class="mt-7">
                            <FeatureVisual variant="score" />
                        </div>
                    </article>

                    <article class="rounded-xl border border-border p-7">
                        <h3 class="text-[1.1rem] font-semibold text-strong">
                            Authorized sources only
                        </h3>
                        <p class="mt-2.5 leading-relaxed text-muted">
                            Every connection runs through an official provider
                            API with explicit permissions. Nothing scrapes
                            profiles or risks your ad accounts.
                        </p>
                        <Link
                            href="/integrations"
                            class="mt-4 inline-flex items-center gap-1.5 text-[0.9rem] font-medium text-strong"
                        >
                            See integrations
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </article>

                    <article class="rounded-xl border border-border p-7">
                        <h3 class="text-[1.1rem] font-semibold text-strong">
                            Automation you choose to trust
                        </h3>
                        <p class="mt-2.5 leading-relaxed text-muted">
                            Recommend, require approval, or run autonomously.
                            Per workflow, with quiet hours, limits and a kill
                            switch.
                        </p>
                        <Link
                            href="/product#automate"
                            class="mt-4 inline-flex items-center gap-1.5 text-[0.9rem] font-medium text-strong"
                        >
                            How it works
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </article>

                    <article
                        class="rounded-xl border border-border bg-surface-alt p-7 lg:col-span-2"
                    >
                        <div
                            class="grid gap-7 sm:grid-cols-[1fr_1.1fr] sm:items-center"
                        >
                            <div>
                                <h3
                                    class="text-[1.1rem] font-semibold text-strong"
                                >
                                    One inbox, every channel
                                </h3>
                                <p class="mt-2.5 leading-relaxed text-muted">
                                    WhatsApp, email and SMS in a single thread
                                    list, with the lead record and open deals
                                    beside each conversation.
                                </p>
                            </div>
                            <FeatureVisual variant="inbox" />
                        </div>
                    </article>

                    <article class="rounded-xl border border-border p-7">
                        <h3 class="text-[1.1rem] font-semibold text-strong">
                            Revenue back to source
                        </h3>
                        <p class="mt-2.5 leading-relaxed text-muted">
                            First touch, last touch and conversion source
                            retained end to end. Cost per qualified lead,
                            computed from events.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ────────────────────── Dual mode ────────────────────── -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-24">
                <SectionLabel index="04" label="Your choice" />

                <div
                    class="mt-10 grid gap-10 lg:grid-cols-[1fr_1.15fr] lg:gap-16"
                >
                    <div>
                        <h2
                            class="max-w-[16ch] font-display text-[1.9rem] leading-[1.12] font-bold tracking-[-0.03em] text-strong sm:text-[2.3rem]"
                        >
                            Replace your CRM, or sit on top of it
                        </h2>
                        <p class="mt-4 max-w-[42ch] leading-relaxed text-muted">
                            Both paths are first-class. Neither is a migration
                            you have to survive before you see any value.
                        </p>
                        <ul class="mt-7 space-y-2.5">
                            <li
                                v-for="item in included"
                                :key="item"
                                class="flex items-start gap-2.5 text-[0.92rem] text-muted"
                            >
                                <Check
                                    class="mt-[0.2rem] size-4 shrink-0 text-primary-600"
                                    aria-hidden="true"
                                />
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-border p-6">
                            <span class="font-mono text-[0.75rem] text-soft"
                                >Option A</span
                            >
                            <h3
                                class="mt-2.5 text-[1.15rem] font-semibold text-strong"
                            >
                                Use the built-in CRM
                            </h3>
                            <p
                                class="mt-2.5 text-[0.92rem] leading-relaxed text-muted"
                            >
                                Leads, contacts, companies, deals, pipelines and
                                quotes. A complete CRM for teams that do not
                                have one, or want to leave one behind.
                            </p>
                        </div>

                        <div
                            class="rounded-xl border-2 p-6"
                            :style="{
                                borderColor: 'var(--brand-primary)',
                                background: 'var(--brand-soft)',
                            }"
                        >
                            <span class="font-mono text-[0.75rem] text-muted"
                                >Option B</span
                            >
                            <h3
                                class="mt-2.5 text-[1.15rem] font-semibold text-strong"
                            >
                                Keep the CRM you have
                            </h3>
                            <p
                                class="mt-2.5 text-[0.92rem] leading-relaxed text-muted"
                            >
                                Salesforce, HubSpot, Zoho, Pipedrive or your own
                                stays the system of record. We handle
                                acquisition, qualification and messaging,
                                syncing both ways on external IDs.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ──────────────────────── CTA ──────────────────────── -->
        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-20 lg:px-8 lg:py-28">
                <div
                    class="grid gap-10 lg:grid-cols-[1fr_1fr] lg:items-center lg:gap-20"
                >
                    <div>
                        <h2
                            class="max-w-[16ch] font-display text-[2rem] leading-[1.08] font-bold tracking-[-0.03em] text-strong sm:text-[2.6rem]"
                        >
                            Stop losing leads between tabs
                        </h2>
                        <p class="mt-4 max-w-[42ch] leading-relaxed text-muted">
                            {{ brand.name }} is in private development. Request
                            early access and we will get in touch before launch.
                            No newsletter, no hard sell.
                        </p>
                        <p
                            class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-[0.9rem]"
                        >
                            <Link
                                href="/pricing"
                                class="font-medium text-strong underline decoration-border underline-offset-4"
                            >
                                See pricing
                            </Link>
                            <Link
                                href="/contact"
                                class="font-medium text-strong underline decoration-border underline-offset-4"
                            >
                                Book a demo
                            </Link>
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-border bg-surface-alt p-7"
                    >
                        <WaitlistForm :brand-name="brand.name" />
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
