<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Ban } from 'lucide-vue-next';
import PageHero from '@/components/marketing/PageHero.vue';
import SectionLabel from '@/components/marketing/SectionLabel.vue';
import Button from '@/components/ui/Button.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

/**
 * Each entry names the actual product being integrated with, and the access
 * it requires. Saying "LinkedIn" without saying "Lead Sync, via their partner
 * programme" sets an expectation the integration cannot meet.
 */
const groups = [
    {
        title: 'Lead sources',
        note: 'Where new leads come from.',
        items: [
            {
                name: 'Facebook / Meta',
                via: 'Marketing API — Lead Ads',
                requires: 'App review',
            },
            {
                name: 'Instagram',
                via: 'Instagram Platform — business accounts',
                requires: 'App review',
            },
            {
                name: 'LinkedIn',
                via: 'Lead Sync API — Lead Gen Forms',
                requires: 'Partner programme',
            },
            {
                name: 'TikTok',
                via: 'Business API — lead generation',
                requires: 'Developer approval',
            },
            {
                name: 'Google Ads',
                via: 'Google Ads API — lead form extensions',
                requires: 'Developer token',
            },
            {
                name: 'Website forms',
                via: 'Embed, JS snippet or REST endpoint',
                requires: 'Nothing',
            },
            {
                name: 'CSV and XLSX',
                via: 'Import wizard with field mapping',
                requires: 'Nothing',
            },
            {
                name: 'Inbound webhooks',
                via: 'POST /api/v1/inbound/leads',
                requires: 'API key',
            },
        ],
    },
    {
        title: 'Messaging',
        note: 'How you reach people back.',
        items: [
            {
                name: 'WhatsApp',
                via: 'WhatsApp Business Platform',
                requires: 'Business verification',
            },
            {
                name: 'Email',
                via: 'SMTP, SES, Postmark, SendGrid, Mailgun',
                requires: 'Sender domain',
            },
            {
                name: 'Microsoft 365 / Gmail',
                via: 'OAuth mailbox connection',
                requires: 'Tenant consent',
            },
            {
                name: 'SMS',
                via: 'Twilio, Vonage and regional gateways',
                requires: 'Provider account',
            },
        ],
    },
    {
        title: 'Calendars',
        note: 'Booking without the back and forth.',
        items: [
            {
                name: 'Google Calendar',
                via: 'Calendar API v3',
                requires: 'OAuth consent',
            },
            {
                name: 'Microsoft Calendar',
                via: 'Graph API',
                requires: 'OAuth consent',
            },
        ],
    },
    {
        title: 'CRM sync',
        note: 'For teams keeping their system of record.',
        items: [
            {
                name: 'Salesforce',
                via: 'REST API, two-way sync',
                requires: 'Connected app',
            },
            {
                name: 'HubSpot',
                via: 'CRM API, two-way sync',
                requires: 'Private app token',
            },
            { name: 'Zoho CRM', via: 'REST API', requires: 'OAuth client' },
            { name: 'Pipedrive', via: 'REST API', requires: 'API token' },
            {
                name: 'Custom CRM',
                via: 'Connector interface plus field mapping',
                requires: 'Your endpoint',
            },
        ],
    },
];

const refusals = [
    'Logging into a personal social account to read profiles',
    'Scraping profiles, followers or contact details from any platform',
    'Bypassing CAPTCHAs, rate limits or anti-bot protection',
    'Buying or ingesting purchased contact lists on your behalf',
    'Collecting data a provider has not authorized us to collect',
];
</script>

<template>
    <Head>
        <title>Integrations — {{ brand.name }}</title>
        <meta
            name="description"
            content="Every connection runs through an official provider API with explicit permissions. What we integrate with, what each requires, and what we will not do."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Integrations"
            title="Official APIs, or nothing"
            lede="Every connection runs through an authorized provider API with permissions you grant explicitly. That constraint is the point: it is what keeps your ad accounts and your data out of trouble."
        >
            <template #actions>
                <Link href="/contact">
                    <Button variant="brand" size="lg">
                        Ask about a provider
                        <ArrowRight class="size-4" />
                    </Button>
                </Link>
            </template>
        </PageHero>

        <!-- Provider tables, grouped. A table rather than a card grid, because
             the interesting columns are "via what" and "needs what" — and a
             logo wall would say none of it. -->
        <div class="mx-auto max-w-[78rem] px-5 lg:px-8">
            <section
                v-for="(group, i) in groups"
                :key="group.title"
                class="border-b border-border py-14"
            >
                <div class="grid gap-8 lg:grid-cols-[18rem_1fr] lg:gap-16">
                    <div class="lg:sticky lg:top-28 lg:self-start">
                        <SectionLabel
                            :index="String(i + 1).padStart(2, '0')"
                            :label="group.title"
                        />
                        <p
                            class="mt-4 max-w-[30ch] text-[0.93rem] leading-relaxed text-muted"
                        >
                            {{ group.note }}
                        </p>
                    </div>

                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-border">
                                <th
                                    class="pb-2.5 text-[0.72rem] font-semibold tracking-[0.1em] text-soft uppercase"
                                >
                                    Provider
                                </th>
                                <th
                                    class="hidden pb-2.5 text-[0.72rem] font-semibold tracking-[0.1em] text-soft uppercase sm:table-cell"
                                >
                                    Via
                                </th>
                                <th
                                    class="pb-2.5 text-right text-[0.72rem] font-semibold tracking-[0.1em] text-soft uppercase"
                                >
                                    Requires
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in group.items"
                                :key="item.name"
                                class="border-b border-border last:border-0"
                            >
                                <td
                                    class="py-3.5 pr-4 text-[0.95rem] font-medium text-strong"
                                >
                                    {{ item.name }}
                                    <span
                                        class="mt-0.5 block text-[0.85rem] font-normal text-muted sm:hidden"
                                    >
                                        {{ item.via }}
                                    </span>
                                </td>
                                <td
                                    class="hidden py-3.5 pr-4 text-[0.9rem] text-muted sm:table-cell"
                                >
                                    {{ item.via }}
                                </td>
                                <td
                                    class="py-3.5 text-right font-mono text-[0.82rem] text-muted"
                                >
                                    {{ item.requires }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- What we refuse. Unusual to put on a marketing page, which is
             exactly why it is persuasive to the people who have been burned. -->
        <section class="bg-[#0b1524] text-slate-300">
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-20">
                <div class="grid gap-10 lg:grid-cols-[26rem_1fr] lg:gap-20">
                    <div>
                        <h2
                            class="max-w-[18ch] font-display text-[1.75rem] leading-[1.12] font-bold tracking-[-0.03em] text-white sm:text-[2.1rem]"
                        >
                            What we will not build, ever
                        </h2>
                        <p
                            class="mt-4 max-w-[38ch] leading-relaxed text-slate-400"
                        >
                            Tools that do these things get accounts banned and
                            create liability you inherit. If that is what you
                            need, we are the wrong platform, and we would rather
                            say so here than after you have paid.
                        </p>
                    </div>

                    <ul
                        class="space-y-0 divide-y divide-slate-800 border-t border-slate-800"
                    >
                        <li
                            v-for="item in refusals"
                            :key="item"
                            class="flex items-start gap-3.5 py-4"
                        >
                            <Ban
                                class="mt-0.5 size-4 shrink-0 text-slate-600"
                                aria-hidden="true"
                            />
                            <span class="leading-relaxed text-slate-300">{{
                                item
                            }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-5 rounded-xl border border-border bg-surface-alt px-7 py-6"
                >
                    <div>
                        <h2 class="text-[1.1rem] font-semibold text-strong">
                            Need something not listed?
                        </h2>
                        <p class="mt-1 max-w-[56ch] text-[0.92rem] text-muted">
                            Anything with a REST API can be wired through the
                            connector interface or inbound webhooks. Tell us
                            what you use.
                        </p>
                    </div>
                    <div class="flex gap-2.5">
                        <Link href="/developers">
                            <Button variant="secondary" size="lg"
                                >Read the API docs</Button
                            >
                        </Link>
                        <Link href="/contact">
                            <Button variant="brand" size="lg">Ask us</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
