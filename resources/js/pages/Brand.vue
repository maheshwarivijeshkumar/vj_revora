<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, ExternalLink, RotateCcw } from 'lucide-vue-next';
import { watch } from 'vue';
import PageHero from '@/components/marketing/PageHero.vue';
import Button from '@/components/ui/Button.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import { useToastStore } from '@/stores/toast';

type BrandCard = {
    key: string;
    name: string;
    tagline: string;
    concept: string;
    primary: string;
    secondary: string;
    accent: string;
    assets: Record<string, string>;
};

const props = defineProps<{
    brands: BrandCard[];
    active: string;
    configured: string;
    homeUrl: string;
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const page = usePage();
const toasts = useToastStore();

/**
 * Re-themes the live document.
 *
 * `data-brand` and the favicon links live on <html> and <head>, which Inertia
 * never re-renders — only the page component is swapped. Without this the
 * colours would not change until a full page load, and forcing one would
 * destroy the Pinia store and take the confirmation toast with it. Applying
 * the change here keeps the re-theme instant and the toast alive.
 */
function paintDocument(key: string): void {
    const brand = props.brands.find((b) => b.key === key);

    if (!brand) {
        return;
    }

    document.documentElement.dataset.brand = key;

    const head = document.head;
    const setHref = (selector: string, href: string | undefined): void => {
        const link = head.querySelector<HTMLLinkElement>(selector);
        if (link && href) {
            link.href = href;
        }
    };

    setHref('link[rel="icon"][type="image/svg+xml"]', brand.assets.favicon_svg);
    setHref('link[rel="icon"][sizes="any"]', brand.assets.favicon_ico);
    setHref('link[rel="apple-touch-icon"]', brand.assets.app_icon_180);

    head.querySelector<HTMLMetaElement>(
        'meta[name="theme-color"]',
    )?.setAttribute('content', brand.primary);
}

/**
 * Applies a brand for this session, then asks whether to go and look at it.
 *
 * Both answers are shown on the toast rather than leaving "stay here" implied
 * by inaction, because the question was asked explicitly. It stays
 * non-blocking: previewing is reversible and session-only, so a modal would be
 * out of proportion (§41 reserves those for critical operations).
 */
function apply(key: string): void {
    const name = props.brands.find((b) => b.key === key)?.name ?? key;

    router.post(
        '/branding',
        { brand: key },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                paintDocument(key);

                toasts.success(`${name} applied`, {
                    description:
                        'Previewing in this session only. Open the home page to see it in context?',
                    // Long enough to read a question and answer it; a four
                    // second success toast is not.
                    duration: 15000,
                    action: {
                        label: 'Go to home page',
                        handler: () => router.visit(props.homeUrl),
                    },
                    secondaryAction: {
                        label: 'Stay here',
                        handler: () => {},
                    },
                });
            },
            onError: () =>
                toasts.error('Could not apply that brand', {
                    description: 'Please try again.',
                }),
        },
    );
}

function reset(): void {
    const name =
        props.brands.find((b) => b.key === props.configured)?.name ??
        props.configured;

    router.delete('/branding', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            paintDocument(props.configured);

            toasts.info(`Reverted to ${name}`, {
                description: 'Your session now matches the configured default.',
            });
        },
    });
}

// Only errors arrive as flash here. Apply and reset raise their own toasts,
// because those carry actions and descriptions a flash string cannot.
watch(
    () => page.props.flash,
    (flash) => {
        const error = (flash as Record<string, string | null> | undefined)
            ?.error;
        if (error) {
            toasts.error(error);
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Head title="Choose a brand" />

    <MarketingLayout :brand="brand" :contact="contact" :social="social">
        <PageHero
            kicker="Branding"
            title="Choose a brand"
            lede="Apply a candidate to preview it across the whole product. This re-themes your session only and leaves the configured default untouched."
        >
            <template #actions>
                <a :href="homeUrl">
                    <Button variant="brand" size="lg">
                        Go to the website
                        <ExternalLink class="size-4" />
                    </Button>
                </a>
                <Button
                    v-if="active !== configured"
                    variant="secondary"
                    size="lg"
                    @click="reset"
                >
                    <RotateCcw class="size-4" />
                    Reset to default
                </Button>
            </template>
        </PageHero>

        <section>
            <div class="mx-auto max-w-[78rem] px-5 py-14 lg:px-8">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="candidate in brands"
                        :key="candidate.key"
                        class="flex flex-col overflow-hidden rounded-xl border bg-surface transition-all duration-200"
                        :class="
                            candidate.key === active
                                ? 'border-primary-500 ring-2 ring-primary-500/25'
                                : 'border-border hover:-translate-y-0.5 hover:shadow-pop'
                        "
                    >
                        <div
                            class="relative h-24"
                            :style="{
                                backgroundImage: `linear-gradient(135deg, ${candidate.primary}, ${candidate.accent})`,
                            }"
                        >
                            <img
                                :src="candidate.assets.app_icon_192"
                                :alt="`${candidate.name} app icon`"
                                class="absolute -bottom-6 left-5 size-14 rounded-xl shadow-pop"
                            />
                            <StatusBadge
                                v-if="candidate.key === configured"
                                tone="info"
                                label="Configured default"
                                class="absolute top-3 right-3"
                            />
                        </div>

                        <div class="flex flex-1 flex-col p-5 pt-9">
                            <h2
                                class="font-display text-[1.25rem] font-bold tracking-[-0.02em] text-strong"
                            >
                                {{ candidate.name }}
                            </h2>
                            <p class="mt-0.5 text-[0.85rem] text-muted">
                                {{ candidate.tagline }}
                            </p>
                            <p
                                class="mt-3 text-[0.88rem] leading-relaxed text-muted"
                            >
                                {{ candidate.concept }}
                            </p>

                            <dl class="mt-4 flex gap-2">
                                <div
                                    v-for="swatch in [
                                        {
                                            label: 'Primary',
                                            value: candidate.primary,
                                        },
                                        {
                                            label: 'Secondary',
                                            value: candidate.secondary,
                                        },
                                        {
                                            label: 'Accent',
                                            value: candidate.accent,
                                        },
                                    ]"
                                    :key="swatch.label"
                                    class="flex-1"
                                >
                                    <div
                                        class="h-8 rounded-md border border-border"
                                        :style="{ background: swatch.value }"
                                    />
                                    <dt class="sr-only">{{ swatch.label }}</dt>
                                    <dd
                                        class="mt-1 font-mono text-[0.68rem] text-muted"
                                    >
                                        {{ swatch.value }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-auto flex items-center gap-2 pt-5">
                                <Button
                                    v-if="candidate.key === active"
                                    variant="secondary"
                                    size="sm"
                                    class="flex-1"
                                    disabled
                                >
                                    <Check class="size-4" />
                                    Applied
                                </Button>
                                <Button
                                    v-else
                                    variant="brand"
                                    size="sm"
                                    class="flex-1"
                                    @click="apply(candidate.key)"
                                >
                                    Apply
                                </Button>

                                <a
                                    :href="candidate.assets.app_icon_512"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-lg border border-border px-3 py-1.5 text-[0.85rem] text-muted transition-colors hover:text-strong"
                                >
                                    Assets
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <section
                    class="mt-10 rounded-xl border border-border bg-surface-alt p-7"
                >
                    <h2 class="text-[1.1rem] font-semibold text-strong">
                        Making a choice permanent
                    </h2>
                    <p
                        class="mt-1.5 max-w-[62ch] text-[0.93rem] leading-relaxed text-muted"
                    >
                        Previewing only affects your own session. To set the
                        default for everyone:
                    </p>
                    <ol class="mt-4 space-y-2.5 text-[0.93rem] text-muted">
                        <li class="flex gap-3">
                            <span class="font-mono text-[0.8rem] text-soft"
                                >1</span
                            >
                            <span>
                                Set
                                <code
                                    class="rounded bg-surface px-1.5 py-0.5 font-mono text-[0.85rem] text-strong"
                                    >BRAND_KEY={{ active }}</code
                                >
                                in
                                <code class="font-mono text-[0.85rem]"
                                    >.env</code
                                >.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="font-mono text-[0.8rem] text-soft"
                                >2</span
                            >
                            <span>
                                Run
                                <code
                                    class="rounded bg-surface px-1.5 py-0.5 font-mono text-[0.85rem] text-strong"
                                    >php artisan config:clear</code
                                >.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="font-mono text-[0.8rem] text-soft"
                                >3</span
                            >
                            <span>
                                Set
                                <code
                                    class="rounded bg-surface px-1.5 py-0.5 font-mono text-[0.85rem] text-strong"
                                    >BRAND_PREVIEW=false</code
                                >
                                to retire this page.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="font-mono text-[0.8rem] text-soft"
                                >4</span
                            >
                            <span>
                                Run trademark, company-name, social-handle and
                                domain checks before any public use. None of
                                these names has been cleared.
                            </span>
                        </li>
                    </ol>
                </section>
            </div>
        </section>
    </MarketingLayout>
</template>
