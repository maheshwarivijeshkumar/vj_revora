<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHero from '@/components/marketing/PageHero.vue';
import SectionLabel from '@/components/marketing/SectionLabel.vue';
import Button from '@/components/ui/Button.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const sections = [
    {
        n: '01',
        title: 'Tenant isolation',
        lede: 'The guarantee everything else depends on. One workspace must never see another, and that has to hold as the codebase grows, not just on the day it was written.',
        rows: [
            {
                term: 'Query-layer enforcement',
                description:
                    'Every tenant-owned model carries a global scope constraining it to the current workspace. There is no unscoped path through the ORM.',
            },
            {
                term: 'Fails closed',
                description:
                    'With no workspace bound, queries return nothing rather than everything. A missing-context bug becomes an empty screen instead of a data leak.',
            },
            {
                term: 'Enforced by a failing build',
                description:
                    'An automated test fails CI if any tenant-owned table lacks its workspace column, index or model trait. Drift is caught the day it is introduced.',
            },
            {
                term: 'One audited escape hatch',
                description:
                    'Cross-workspace reads exist only for platform administration, through a single explicit call that is narrow enough to review.',
            },
        ],
    },
    {
        n: '02',
        title: 'Credentials and secrets',
        lede: 'You are handing us OAuth tokens for your ad accounts. Those are worth more than most of the data they unlock.',
        rows: [
            {
                term: 'Encrypted at rest',
                description:
                    'Provider access and refresh tokens are encrypted in the database. Application secrets live in a secrets manager, not in environment files on disk.',
            },
            {
                term: 'Never logged',
                description:
                    'A log processor redacts known-sensitive keys, so a careless debug statement cannot spill a token into your log aggregator.',
            },
            {
                term: 'API keys shown once',
                description:
                    'Only a prefix and a hash are stored. A key that is lost is rotated, never recovered, because we genuinely cannot recover it.',
            },
            {
                term: 'Scoped, not all-or-nothing',
                description:
                    'Keys carry explicit scopes such as leads.read or messages.send, so an integration gets exactly the access it needs.',
            },
        ],
    },
    {
        n: '03',
        title: 'Data in and out',
        lede: 'Every external event is treated as untrusted until proven otherwise, and every outbound delivery is verifiable at the far end.',
        rows: [
            {
                term: 'Signature verification before processing',
                description:
                    'Inbound webhooks are verified before they are queued, so an unsigned payload never reaches business logic.',
            },
            {
                term: 'Idempotent by construction',
                description:
                    'A unique constraint on provider event IDs makes replay a no-op. Retried deliveries cannot duplicate a lead or a charge.',
            },
            {
                term: 'Signed outbound webhooks',
                description:
                    'Your endpoint can verify the payload came from us. Failures retry with exponential backoff, log every attempt and can be replayed.',
            },
            {
                term: 'Rate limiting at four levels',
                description:
                    'Per workspace, per API key, per endpoint and per IP, with standard rate-limit headers returned.',
            },
        ],
    },
    {
        n: '04',
        title: 'Accountability',
        lede: 'When someone asks what happened to a record six weeks ago, there should be an answer rather than an inference.',
        rows: [
            {
                term: 'Full audit trail',
                description:
                    'Actor, action, entity, the before and after state, IP address, user agent and timestamp, on every consequential action.',
            },
            {
                term: 'Granular permissions',
                description:
                    'Roles compose from individual permissions per module and action. The interface hides what the server would refuse.',
            },
            {
                term: 'AI actions are attributable',
                description:
                    'Every automated message records which workflow or agent sent it and why, in plain language a person can read.',
            },
            {
                term: 'Consent held per person',
                description:
                    'Opt-out is keyed to the email address or phone number, not the lead record, so it survives duplicates, merges and re-imports.',
            },
        ],
    },
];
</script>

<template>
    <Head>
        <title>Security — {{ brand.name }}</title>
        <meta
            name="description"
            content="Tenant isolation, credential handling, webhook verification and audit. How the platform is built, in specifics."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Security"
            title="Built multi-tenant from the first commit"
            lede="Isolation retrofitted into a working product is where the expensive mistakes live. This page is specific on purpose: vague reassurance is not something you can evaluate."
        />

        <div class="mx-auto max-w-[78rem] px-5 lg:px-8">
            <section
                v-for="section in sections"
                :key="section.n"
                class="border-b border-border py-14 lg:py-16"
            >
                <div class="grid gap-8 lg:grid-cols-[22rem_1fr] lg:gap-16">
                    <div class="lg:sticky lg:top-28 lg:self-start">
                        <SectionLabel
                            :index="section.n"
                            :label="section.title"
                        />
                        <p class="mt-5 max-w-[36ch] leading-relaxed text-muted">
                            {{ section.lede }}
                        </p>
                    </div>

                    <dl class="divide-y divide-border border-t border-border">
                        <div
                            v-for="row in section.rows"
                            :key="row.term"
                            class="py-5"
                        >
                            <dt class="text-[1rem] font-semibold text-strong">
                                {{ row.term }}
                            </dt>
                            <dd
                                class="mt-1.5 max-w-[64ch] text-[0.94rem] leading-relaxed text-muted"
                            >
                                {{ row.description }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <!-- Honest status. Claiming certifications a pre-launch product does
             not hold would be exactly the kind of thing this page argues
             against. -->
        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div class="rounded-xl border border-border bg-surface-alt p-7">
                    <h2 class="text-[1.1rem] font-semibold text-strong">
                        On certifications
                    </h2>
                    <p class="mt-2.5 max-w-[68ch] leading-relaxed text-muted">
                        {{ brand.name }} is in private development and does not
                        hold SOC 2, ISO 27001 or any other audit certification
                        yet. We are not going to display badges we have not
                        earned. The controls above are implemented and testable
                        today; formal audit follows once the platform is in
                        production with customers whose requirements shape the
                        scope.
                    </p>
                    <p class="mt-4 leading-relaxed text-muted">
                        If your procurement process needs something specific,
                        tell us early and we will be straight with you about
                        where we are.
                    </p>
                    <Link href="/contact" class="mt-6 inline-block">
                        <Button variant="secondary" size="lg">
                            Talk to us about compliance
                        </Button>
                    </Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
