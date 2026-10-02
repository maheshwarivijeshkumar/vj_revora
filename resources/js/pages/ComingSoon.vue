<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BrainCircuit,
    Check,
    Inbox,
    MessageCircle,
    Moon,
    Sun,
    Workflow,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import ToastHost from '@/components/overlay/ToastHost.vue';
import Button from '@/components/ui/Button.vue';
import { useThemeStore } from '@/stores/theme';
import { useToastStore } from '@/stores/toast';

const props = defineProps<{
    brand: {
        key: string;
        name: string;
        tagline: string;
        assets: Record<string, string>;
    };
    launchAt: string | null;
}>();

const page = usePage();
const theme = useThemeStore();
const toasts = useToastStore();

const submitted = ref(false);

const form = useForm({
    email: '',
    consent: false,
    landing_page: '',
    // Honeypot. Hidden from people, irresistible to naive bots.
    website: '',
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
});

onMounted(() => {
    theme.init();

    // Attribution is captured at the moment of intent (§16). Reading it on
    // submit would lose it for anyone who navigates within the page first.
    const params = new URLSearchParams(window.location.search);
    form.landing_page = window.location.href;
    form.utm_source = params.get('utm_source') ?? '';
    form.utm_medium = params.get('utm_medium') ?? '';
    form.utm_campaign = params.get('utm_campaign') ?? '';
});

function submit(): void {
    form.post('/waitlist', {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset('email', 'consent');
        },
        onError: (errors) => {
            if (errors.website) {
                toasts.error('Something went wrong. Please try again.');
            }
        },
    });
}

// --- Countdown --------------------------------------------------------------

const remaining = ref<{
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
} | null>(null);

function tick(): void {
    if (!props.launchAt) return;

    const diff = new Date(props.launchAt).getTime() - Date.now();

    if (diff <= 0) {
        remaining.value = null;
        return;
    }

    remaining.value = {
        days: Math.floor(diff / 86_400_000),
        hours: Math.floor((diff / 3_600_000) % 24),
        minutes: Math.floor((diff / 60_000) % 60),
        seconds: Math.floor((diff / 1000) % 60),
    };
}

onMounted(() => {
    if (!props.launchAt) return;
    tick();
    const timer = setInterval(tick, 1000);
    // The page is never navigated away from within the SPA, but clearing on
    // unload keeps the interval from outliving a hot reload in development.
    window.addEventListener('beforeunload', () => clearInterval(timer));
});

const countdown = computed(() =>
    remaining.value === null
        ? []
        : [
              { label: 'Days', value: remaining.value.days },
              { label: 'Hours', value: remaining.value.hours },
              { label: 'Minutes', value: remaining.value.minutes },
              { label: 'Seconds', value: remaining.value.seconds },
          ],
);

const pillars = [
    {
        icon: Zap,
        title: 'Capture',
        body: 'Official integrations with Meta, LinkedIn, TikTok, Google and your own website forms.',
    },
    {
        icon: BrainCircuit,
        title: 'Qualify',
        body: 'Explainable AI scoring that tells you why a lead is hot, not just that it is.',
    },
    {
        icon: Inbox,
        title: 'Engage',
        body: 'WhatsApp, email and SMS in one inbox, with the full lead context beside every thread.',
    },
    {
        icon: Workflow,
        title: 'Automate',
        body: 'Visual workflows that run manually, on approval, or autonomously — your call.',
    },
];
</script>

<template>
    <Head>
        <title>{{ brand.name }} — Coming Soon</title>
        <meta name="description" :content="brand.tagline" />
    </Head>

    <div class="relative min-h-dvh overflow-hidden bg-page">
        <!--
          Ambient background. Built from brand tokens rather than fixed colours,
          so it re-themes with the selected identity and in dark mode.
        -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div
                class="absolute -top-40 -left-40 size-[36rem] rounded-full opacity-[0.18] blur-3xl dark:opacity-25"
                :style="{ background: 'var(--brand-primary)' }"
            />
            <div
                class="absolute -right-40 -bottom-52 size-[40rem] rounded-full opacity-[0.14] blur-3xl dark:opacity-20"
                :style="{ background: 'var(--brand-accent)' }"
            />
            <div
                class="absolute inset-0 opacity-[0.035] dark:opacity-[0.05]"
                style="
                    background-image:
                        linear-gradient(
                            var(--text-strong) 1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            90deg,
                            var(--text-strong) 1px,
                            transparent 1px
                        );
                    background-size: 56px 56px;
                "
            />
        </div>

        <div
            class="relative mx-auto flex min-h-dvh max-w-6xl flex-col px-4 sm:px-6"
        >
            <!-- Header -->
            <header class="flex items-center justify-between py-6">
                <div class="flex items-center gap-2.5">
                    <img
                        :src="brand.assets.favicon_svg"
                        alt=""
                        class="size-8"
                    />
                    <span class="font-display text-h3 font-bold text-strong">
                        {{ brand.name }}
                    </span>
                </div>

                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-[var(--radius-control)] border border-border bg-surface text-muted transition-colors hover:text-strong"
                    :aria-label="
                        theme.isDark
                            ? 'Switch to light theme'
                            : 'Switch to dark theme'
                    "
                    @click="theme.toggle()"
                >
                    <component
                        :is="theme.isDark ? Sun : Moon"
                        class="size-4.5"
                    />
                </button>
            </header>

            <!-- Hero -->
            <main class="flex flex-1 flex-col justify-center py-10 sm:py-16">
                <div class="max-w-3xl">
                    <span
                        class="inline-flex items-center gap-2 rounded-[var(--radius-pill)] border border-border bg-surface px-3 py-1 text-caption font-medium text-muted"
                    >
                        <span class="relative flex size-2">
                            <span
                                class="absolute inline-flex size-full animate-ping rounded-full opacity-60"
                                :style="{ background: 'var(--brand-primary)' }"
                            />
                            <span
                                class="relative inline-flex size-2 rounded-full"
                                :style="{ background: 'var(--brand-primary)' }"
                            />
                        </span>
                        In private development
                    </span>

                    <h1
                        class="mt-6 font-display text-[2.5rem] leading-[1.1] font-bold text-strong sm:text-[3.5rem]"
                    >
                        Every lead,
                        <span class="brand-gradient-text"
                            >captured and converted.</span
                        >
                    </h1>

                    <p class="mt-5 max-w-xl text-body-lg text-muted sm:text-lg">
                        {{ brand.name }} is an AI-powered omnichannel lead
                        generation, CRM and sales automation platform.
                        {{ brand.tagline }}
                    </p>

                    <!-- Countdown -->
                    <div
                        v-if="countdown.length"
                        class="mt-9 flex flex-wrap gap-3"
                    >
                        <div
                            v-for="unit in countdown"
                            :key="unit.label"
                            class="min-w-[4.5rem] rounded-[var(--radius-card)] border border-border bg-surface px-4 py-3 text-center shadow-card"
                        >
                            <div
                                class="text-h2 font-bold text-strong tabular-nums"
                            >
                                {{ String(unit.value).padStart(2, '0') }}
                            </div>
                            <div
                                class="text-caption tracking-wide text-muted uppercase"
                            >
                                {{ unit.label }}
                            </div>
                        </div>
                    </div>

                    <!-- Waitlist -->
                    <div class="mt-9">
                        <WaitlistForm :brand-name="brand.name" />
                    </div>
                </div>

                <!-- Pillars -->
                <ul class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <li
                        v-for="pillar in pillars"
                        :key="pillar.title"
                        class="rounded-[var(--radius-card)] border border-border bg-surface p-5 shadow-card transition-transform duration-200 hover:-translate-y-0.5"
                    >
                        <div
                            class="flex size-9 items-center justify-center rounded-[var(--radius-control)] text-primary-600"
                            :style="{ background: 'var(--brand-soft)' }"
                        >
                            <component
                                :is="pillar.icon"
                                class="size-4.5"
                                aria-hidden="true"
                            />
                        </div>
                        <h2 class="mt-3.5 font-semibold text-body text-strong">
                            {{ pillar.title }}
                        </h2>
                        <p class="mt-1 text-small text-muted">
                            {{ pillar.body }}
                        </p>
                    </li>
                </ul>
            </main>

            <footer
                class="flex flex-col gap-2 border-t border-border py-6 text-small text-muted sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    &copy; {{ new Date().getFullYear() }} {{ brand.name }}. All
                    rights reserved.
                </p>
                <p class="flex items-center gap-1.5">
                    <MessageCircle class="size-3.5" aria-hidden="true" />
                    Built on official, authorized integrations only.
                </p>
            </footer>
        </div>

        <ToastHost />
    </div>
</template>
