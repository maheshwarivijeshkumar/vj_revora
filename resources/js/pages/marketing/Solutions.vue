<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { ref } from 'vue';
import PageHero from '@/components/marketing/PageHero.vue';
import SectionLabel from '@/components/marketing/SectionLabel.vue';
import Button from '@/components/ui/Button.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const roles = [
    {
        key: 'sales',
        label: 'Sales teams',
        headline: 'Stop reps deciding who to call by scrolling',
        body: 'Leads arrive scored, routed and owned. The rep opens one queue, sees why a lead is worth their time, and has the full conversation history beside it before they dial.',
        wins: [
            'Round robin, territory, product or score-based assignment',
            'SLA timers with escalation when first response slips',
            'Every call, email and message on the record automatically',
            'Pipeline and forecast that reflect what is actually happening',
        ],
    },
    {
        key: 'marketing',
        label: 'Marketing teams',
        headline: 'Defend the budget with numbers, not anecdotes',
        body: 'Attribution survives the whole journey. You can answer which campaign produced qualified pipeline, not just which produced form fills, and you can answer it without a data team.',
        wins: [
            'Cost per lead and cost per qualified lead by campaign',
            'Revenue attributed to source, ad and form',
            'Lead quality by channel, so you can cut what looks cheap but converts badly',
            'Nurture sequences that stop when someone replies',
        ],
    },
    {
        key: 'agency',
        label: 'Agencies',
        headline: 'Run every client in its own isolated workspace',
        body: 'Multi-tenancy is the foundation, not a later add-on. Each client gets separate data, users, roles, pipelines, integrations and billing, and no workspace can read another.',
        wins: [
            'Isolation enforced at the query layer and covered by automated tests',
            'Per-client plans, limits and feature entitlements',
            'White labelling on higher tiers: your logo, domain and sender',
            'Consolidated billing across the workspaces you manage',
        ],
    },
    {
        key: 'existing-crm',
        label: 'Teams with a CRM',
        headline: 'Add acquisition and AI without a migration',
        body: 'Salesforce, HubSpot, Zoho or Pipedrive stays your system of record. We sit in front of it: capturing, deduplicating, qualifying and messaging, then syncing back on external IDs.',
        wins: [
            'Two-way sync with configurable field mapping and conflict strategy',
            'No duplicate records, because external IDs are matched on both sides',
            'Your reps keep working in the CRM they already know',
            'Turn it off and your CRM is exactly as it was',
        ],
    },
];

const active = ref(roles[0].key);

const industries = [
    {
        name: 'Real estate',
        note: 'High enquiry volume, short response window, WhatsApp-first buyers.',
    },
    {
        name: 'Education',
        note: 'Seasonal intake peaks, long nurture cycles, multi-channel parents.',
    },
    {
        name: 'Healthcare services',
        note: 'Appointment-led conversion with consent and privacy obligations.',
    },
    {
        name: 'Financial services',
        note: 'Qualification-heavy, audit trail required on every interaction.',
    },
    {
        name: 'Automotive',
        note: 'Test-drive bookings, dealer routing, ad spend that needs justifying.',
    },
    {
        name: 'B2B software',
        note: 'Demo requests, account matching and pipeline that finance trusts.',
    },
];
</script>

<template>
    <Head>
        <title>Solutions — {{ brand.name }}</title>
        <meta
            name="description"
            content="How sales teams, marketing teams, agencies and teams with an existing CRM use the platform."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Solutions"
            title="Same platform, four very different jobs"
            lede="The mechanics do not change. What changes is which part of the pipeline you are being judged on, so this is how the platform looks from each seat."
        />

        <!-- Role switcher. A tab set rather than four stacked sections: it
             respects that most visitors care about exactly one of these. -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 lg:px-8">
                <div
                    class="flex gap-1 overflow-x-auto border-b border-border"
                    role="tablist"
                    aria-label="Audience"
                >
                    <button
                        v-for="role in roles"
                        :key="role.key"
                        type="button"
                        role="tab"
                        :aria-selected="active === role.key"
                        class="-mb-px shrink-0 border-b-2 px-4 py-4 text-[0.95rem] font-medium transition-colors"
                        :class="
                            active === role.key
                                ? 'border-primary-600 text-strong'
                                : 'border-transparent text-muted hover:text-strong'
                        "
                        @click="active = role.key"
                    >
                        {{ role.label }}
                    </button>
                </div>

                <div
                    v-for="role in roles"
                    v-show="active === role.key"
                    :key="role.key"
                    role="tabpanel"
                    class="grid gap-10 py-14 lg:grid-cols-[1fr_1fr] lg:gap-20 lg:py-20"
                >
                    <div>
                        <h2
                            class="max-w-[18ch] font-display text-[1.85rem] leading-[1.1] font-bold tracking-[-0.03em] text-strong sm:text-[2.3rem]"
                        >
                            {{ role.headline }}
                        </h2>
                        <p
                            class="mt-5 max-w-[52ch] text-[1.02rem] leading-relaxed text-muted"
                        >
                            {{ role.body }}
                        </p>
                        <Link href="/contact" class="mt-8 inline-block">
                            <Button variant="brand" size="lg">
                                Book a demo
                                <ArrowRight class="size-4" />
                            </Button>
                        </Link>
                    </div>

                    <ul class="divide-y divide-border border-t border-border">
                        <li
                            v-for="(win, i) in role.wins"
                            :key="win"
                            class="flex gap-5 py-5"
                        >
                            <span
                                class="font-mono text-[0.8rem] text-soft tabular-nums"
                            >
                                {{ String(i + 1).padStart(2, '0') }}
                            </span>
                            <span
                                class="text-[0.98rem] leading-relaxed text-body"
                            >
                                {{ win }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Industries -->
        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-20">
                <SectionLabel index="02" label="Where it fits" />
                <div class="mt-5 grid gap-8 lg:grid-cols-[24rem_1fr] lg:gap-16">
                    <div>
                        <h2
                            class="max-w-[18ch] font-display text-[1.75rem] leading-[1.12] font-bold tracking-[-0.03em] text-strong"
                        >
                            Built for anywhere a fast reply decides the sale
                        </h2>
                        <p class="mt-4 max-w-[40ch] leading-relaxed text-muted">
                            Nothing here is industry-specific. These are simply
                            the sectors where the gap between enquiry and
                            response is worth the most money.
                        </p>
                    </div>

                    <dl class="grid gap-x-10 gap-y-0 sm:grid-cols-2">
                        <div
                            v-for="industry in industries"
                            :key="industry.name"
                            class="border-t border-border py-5"
                        >
                            <dt class="text-[1rem] font-semibold text-strong">
                                {{ industry.name }}
                            </dt>
                            <dd
                                class="mt-1.5 text-[0.92rem] leading-relaxed text-muted"
                            >
                                {{ industry.note }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
