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

const beliefs = [
    {
        n: '01',
        title: 'A score you cannot question is a score you cannot use',
        body: 'Plenty of tools will hand a rep a number. Almost none will say why. We show the reasons on every score, because a rep who disagrees with the model usually knows something it does not, and the product should let them act on that.',
    },
    {
        n: '02',
        title: 'Automation should be something you turn up, not switch on',
        body: 'Fully autonomous out of the box means either it is trivial or you are about to find out the hard way. The same engine runs in recommend, approve and autonomous modes so you can move the dial as trust is earned.',
    },
    {
        n: '03',
        title: 'The constraint is the feature',
        body: 'Refusing to scrape is not a limitation we are apologising for. Tools that do it get accounts banned and hand you liability. Official APIs are slower to get approved and considerably harder to lose everything with.',
    },
    {
        n: '04',
        title: 'Nobody should have to migrate to get value',
        body: 'Most lead tooling assumes you will abandon your CRM. Most teams will not, and should not. Sitting in front of an existing system is a first-class path, not a compatibility mode.',
    },
];
</script>

<template>
    <Head>
        <title>About — {{ brand.name }}</title>
        <meta
            name="description"
            content="Why we are building a lead operating system, and the opinions baked into it."
        />
    </Head>

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="About"
            :title="`Why ${brand.name} exists`"
            lede="Lead tooling has mostly split into two camps: CRMs that assume the lead is already in the building, and scrapers that will eventually get your ad account banned. We are building the part in between."
        />

        <section class="border-b border-border">
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8 lg:py-20">
                <div class="grid gap-10 lg:grid-cols-[22rem_1fr] lg:gap-20">
                    <div class="lg:sticky lg:top-28 lg:self-start">
                        <SectionLabel index="01" label="What we think" />
                    </div>

                    <div class="divide-y divide-border border-t border-border">
                        <article
                            v-for="belief in beliefs"
                            :key="belief.n"
                            class="py-8"
                        >
                            <div class="flex gap-5">
                                <span
                                    class="shrink-0 font-mono text-[0.82rem] text-soft tabular-nums"
                                >
                                    {{ belief.n }}
                                </span>
                                <div>
                                    <h2
                                        class="max-w-[28ch] font-display text-[1.3rem] leading-snug font-bold tracking-[-0.02em] text-strong"
                                    >
                                        {{ belief.title }}
                                    </h2>
                                    <p
                                        class="mt-3 max-w-[62ch] leading-relaxed text-muted"
                                    >
                                        {{ belief.body }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <!-- Status. Saying plainly where the product is beats implying it is
             further along than it is. -->
        <section class="bg-[#0b1524] text-slate-300">
            <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8 lg:py-20">
                <div class="grid gap-10 lg:grid-cols-[26rem_1fr] lg:gap-20">
                    <div>
                        <h2
                            class="max-w-[16ch] font-display text-[1.75rem] leading-[1.12] font-bold tracking-[-0.03em] text-white sm:text-[2.1rem]"
                        >
                            Where we actually are
                        </h2>
                    </div>
                    <div
                        class="max-w-[62ch] space-y-4 leading-relaxed text-slate-400"
                    >
                        <p>
                            {{ brand.name }} is in private development. The
                            foundations are built and tested: multi-tenancy with
                            isolation enforced at the query layer, roles and
                            permissions, subscription entitlements, and the
                            design system the product runs on.
                        </p>
                        <p>
                            Provider integrations are next, and several of them
                            depend on developer programme approval we do not
                            control. We would rather tell you that than publish
                            a launch date we cannot hold.
                        </p>
                        <p>
                            Early access means genuine access, and a direct line
                            to the people building it. If you have strong
                            opinions about lead tooling, this is the useful time
                            to share them.
                        </p>
                    </div>
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
                            Disagree with any of this?
                        </h2>
                        <p class="mt-1 max-w-[54ch] text-[0.92rem] text-muted">
                            Genuinely useful to hear. The product is still soft
                            enough to change.
                        </p>
                    </div>
                    <Link href="/contact">
                        <Button variant="brand" size="lg">Get in touch</Button>
                    </Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
