<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Copy } from 'lucide-vue-next';
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

const snippet = `curl -X POST https://api.example.com/v1/inbound/leads \\
  -H "Authorization: Bearer $API_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{
    "external_id": "CRM-8421",
    "first_name": "Amara",
    "last_name": "Okafor",
    "email": "amara@acme.example",
    "phone": "+971500000000",
    "source": "partner_site",
    "utm": { "utm_source": "newsletter" }
  }'

# 202 Accepted
# { "ingestion_id": "ing_01JA2...", "status": "queued" }`;

const copied = ref(false);

async function copySnippet(): Promise<void> {
    try {
        await navigator.clipboard.writeText(snippet);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard access is blocked in some contexts; the code is selectable
        // on screen either way, so there is nothing useful to report.
    }
}

const endpoints = [
    { method: 'POST', path: '/v1/leads', note: 'Create a lead' },
    { method: 'GET', path: '/v1/leads', note: 'List and filter' },
    { method: 'PATCH', path: '/v1/leads/{id}', note: 'Update fields' },
    {
        method: 'POST',
        path: '/v1/inbound/leads',
        note: 'Async ingestion, returns an id',
    },
    {
        method: 'GET',
        path: '/v1/conversations',
        note: 'Threads across channels',
    },
    { method: 'POST', path: '/v1/messages', note: 'Send on a channel' },
    { method: 'POST', path: '/v1/appointments', note: 'Book a slot' },
    { method: 'GET', path: '/v1/usage', note: 'Current metering' },
];

const events = [
    'lead.created',
    'lead.qualified',
    'lead.assigned',
    'lead.converted',
    'contact.created',
    'deal.created',
    'deal.won',
    'deal.lost',
    'message.received',
    'message.sent',
    'appointment.created',
    'appointment.cancelled',
    'automation.completed',
    'automation.failed',
    'subscription.updated',
];

const principles = [
    {
        term: 'Versioned, and it stays versioned',
        description:
            'Everything sits under /v1. Breaking changes ship as a new version rather than as a surprise on a Tuesday.',
    },
    {
        term: 'The UI has no private API',
        description:
            'Inertia controllers and API controllers call the same domain actions, so the public surface cannot quietly fall behind what the interface can do.',
    },
    {
        term: 'Scoped keys',
        description:
            'leads.read, leads.write, messages.send and so on. Keys show their secret once, then only a prefix and hash are kept.',
    },
    {
        term: 'Idempotent writes',
        description:
            'Send an idempotency key on anything consequential. Retries are safe, which matters when a timeout leaves you unsure whether it worked.',
    },
    {
        term: 'Signed webhooks with replay',
        description:
            'Fifteen event types, signature verification, exponential backoff, delivery logs and a replay button for when your endpoint was down.',
    },
    {
        term: 'Generated OpenAPI',
        description:
            'The spec comes from the code, not from a document someone remembers to update.',
    },
];
</script>

<template>
    <Head>
        <title>Developers — {{ brand.name }}</title>
        <meta
            name="description"
            content="A versioned REST API, scoped keys, signed webhooks and generated OpenAPI. Built as a first-class surface, not an afterthought."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Developers"
            title="The API is not a bolt-on"
            lede="The developer portal, inbound ingestion and every CRM connector run on the same public surface you get. That is the only reliable way to keep an API honest."
        />

        <!-- Code first. A developer page that opens with marketing copy instead
             of a request is missing its audience. -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[1fr_1.15fr] lg:gap-14">
                    <div>
                        <SectionLabel index="01" label="Ingest a lead" />
                        <h2
                            class="mt-5 max-w-[18ch] font-display text-[1.75rem] leading-[1.12] font-bold tracking-[-0.03em] text-strong"
                        >
                            One POST, and the pipeline takes it from there
                        </h2>
                        <p class="mt-4 max-w-[44ch] leading-relaxed text-muted">
                            Ingestion returns immediately with an id.
                            Normalization, deduplication, scoring, assignment
                            and any triggered automation happen on the queue, so
                            your request never waits on them.
                        </p>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-border bg-[#0b1524]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-800 px-4 py-2.5"
                        >
                            <span
                                class="font-mono text-[0.78rem] text-slate-500"
                            >
                                POST /v1/inbound/leads
                            </span>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded px-2 py-1 font-mono text-[0.75rem] text-slate-400 transition-colors hover:text-slate-200"
                                @click="copySnippet"
                            >
                                <component
                                    :is="copied ? Check : Copy"
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ copied ? 'copied' : 'copy' }}
                            </button>
                        </div>
                        <pre
                            class="overflow-x-auto p-4 text-[0.8rem] leading-relaxed text-slate-300"
                        ><code>{{ snippet }}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Endpoints and events side by side -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <SectionLabel index="02" label="Endpoints" />
                        <table class="mt-6 w-full border-collapse text-left">
                            <tbody>
                                <tr
                                    v-for="endpoint in endpoints"
                                    :key="endpoint.path"
                                    class="border-b border-border"
                                >
                                    <td class="py-3 pr-3 align-top">
                                        <span
                                            class="font-mono text-[0.75rem] font-semibold"
                                            :class="
                                                endpoint.method === 'GET'
                                                    ? 'text-info-strong'
                                                    : 'text-primary-600'
                                            "
                                        >
                                            {{ endpoint.method }}
                                        </span>
                                    </td>
                                    <td
                                        class="py-3 pr-4 font-mono text-[0.83rem] text-strong"
                                    >
                                        {{ endpoint.path }}
                                    </td>
                                    <td
                                        class="py-3 text-right text-[0.85rem] text-muted"
                                    >
                                        {{ endpoint.note }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-4 text-[0.85rem] text-soft">
                            Contacts, companies, deals, tasks, pipelines and
                            users follow the same shape.
                        </p>
                    </div>

                    <div>
                        <SectionLabel index="03" label="Webhook events" />
                        <ul class="mt-6 flex flex-wrap gap-2">
                            <li
                                v-for="event in events"
                                :key="event"
                                class="rounded-md border border-border px-2.5 py-1.5 font-mono text-[0.8rem] text-muted"
                            >
                                {{ event }}
                            </li>
                        </ul>
                        <p
                            class="mt-5 max-w-[46ch] text-[0.9rem] leading-relaxed text-muted"
                        >
                            Subscribe per workspace. Deliveries are signed,
                            retried with exponential backoff, logged with
                            response bodies, and replayable. Endpoints that fail
                            repeatedly are disabled with a notification rather
                            than retried forever.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Principles -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <SectionLabel index="04" label="How it behaves" />
                <dl class="mt-8 grid gap-x-16 gap-y-0 sm:grid-cols-2">
                    <div
                        v-for="item in principles"
                        :key="item.term"
                        class="border-t border-border py-5"
                    >
                        <dt class="text-[0.98rem] font-semibold text-strong">
                            {{ item.term }}
                        </dt>
                        <dd
                            class="mt-1.5 text-[0.92rem] leading-relaxed text-muted"
                        >
                            {{ item.description }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-5 rounded-xl border border-border bg-surface-alt px-7 py-6"
                >
                    <div>
                        <h2 class="text-[1.1rem] font-semibold text-strong">
                            Full reference at launch
                        </h2>
                        <p class="mt-1 max-w-[58ch] text-[0.92rem] text-muted">
                            The developer portal ships with generated OpenAPI
                            docs, a sandbox workspace, request logs and a
                            changelog. Early access includes API credentials.
                        </p>
                    </div>
                    <Link href="/contact">
                        <Button variant="brand" size="lg"
                            >Request early access</Button
                        >
                    </Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
