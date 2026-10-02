<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Clock,
    Compass,
    Copy,
    Home,
    LifeBuoy,
    RefreshCw,
    ServerCrash,
    ShieldAlert,
    Sparkles,
    Timer,
} from 'lucide-vue-next';
import { computed, onMounted, ref, type Component } from 'vue';
import Button from '@/components/ui/Button.vue';
import ToastHost from '@/components/overlay/ToastHost.vue';
import { useThemeStore } from '@/stores/theme';
import { useToastStore } from '@/stores/toast';

const props = defineProps<{
    status: number;
    message?: string | null;
    /** Correlation id for the logged exception. Absent for client-side errors. */
    reference?: string | null;
    /** Seconds until a rate limit or maintenance window clears. */
    retryAfter?: number | null;
    brand: { name: string; assets: Record<string, string> };
    /** False when the visitor has no session to return to. */
    authenticated: boolean;
}>();

const page = usePage();
const theme = useThemeStore();
const toasts = useToastStore();

onMounted(() => theme.init());

type Copy = {
    icon: Component;
    title: string;
    body: string;
    tone: string;
    /** Retrying a 404 or 403 just repeats the same answer. */
    retry: boolean;
};

/**
 * Error copy follows §59: what happened, why, and what the visitor can
 * actually do about it. No stack traces, no internal identifiers beyond an
 * opaque reference, and no blame.
 */
const COPY: Record<number, Copy> = {
    401: {
        icon: ShieldAlert,
        title: 'Please sign in',
        body: 'Your session has ended. Sign in again to pick up where you left off.',
        tone: 'text-info',
        retry: false,
    },
    402: {
        icon: Sparkles,
        title: 'Your plan does not include this',
        body: 'This feature is available on a higher plan, or you have reached a usage limit. Check your billing settings to see what is included.',
        tone: 'text-warning',
        retry: false,
    },
    403: {
        icon: Ban,
        title: "You don't have access to this",
        body: 'Your role does not include permission for this page. If you think it should, ask a workspace administrator to update your role.',
        tone: 'text-warning',
        retry: false,
    },
    404: {
        icon: Compass,
        title: 'We could not find that page',
        body: 'The link may be out of date, or the record may have been moved, renamed or deleted.',
        tone: 'text-info',
        retry: false,
    },
    419: {
        icon: Clock,
        title: 'Your session expired',
        body: 'For security, sessions time out after a period of inactivity. Reload the page and try again — anything you had saved is safe.',
        tone: 'text-warning',
        retry: true,
    },
    429: {
        icon: Timer,
        title: 'Too many requests',
        body: 'You have made a lot of requests in a short time. Wait a moment and try again.',
        tone: 'text-warning',
        retry: true,
    },
    500: {
        icon: ServerCrash,
        title: 'Something went wrong on our end',
        body: 'This is our fault, not yours. The error has been logged and the team notified. Try again in a moment.',
        tone: 'text-danger',
        retry: true,
    },
    503: {
        icon: RefreshCw,
        title: 'Down for maintenance',
        body: 'We are deploying an update and will be back shortly. Nothing has been lost.',
        tone: 'text-info',
        retry: true,
    },
};

const FALLBACK: Copy = {
    icon: ServerCrash,
    title: 'Something went wrong',
    body: 'An unexpected error occurred. Try again, or head back to somewhere familiar.',
    tone: 'text-danger',
    retry: true,
};

const copy = computed<Copy>(() => COPY[props.status] ?? FALLBACK);

/**
 * Statuses whose server message is worth showing.
 *
 * These are the ones the application itself authors — an entitlement failure
 * says "Your plan does not include this feature", which is far more useful
 * than generic copy.
 *
 * 404 and 500 are excluded deliberately: their messages come from the
 * framework and leak internals. A missing route yields "The route nope could
 * not be found", and a missing record yields "No query results for model
 * [App\Models\Lead]" — a class name no visitor should ever see.
 */
const USE_SERVER_MESSAGE = new Set([401, 402, 403, 429, 503]);

const body = computed(() => {
    const message = props.message?.trim();

    if (!message || !USE_SERVER_MESSAGE.has(props.status)) {
        return copy.value.body;
    }

    // Framework one-liners say less than our own copy.
    const generic = new Set([
        'Forbidden',
        'Unauthorized',
        'Service Unavailable',
        'Too Many Requests',
        'Payment Required',
    ]);

    return generic.has(message) ? copy.value.body : message;
});

// --- Retry-after countdown --------------------------------------------------

const seconds = ref(props.retryAfter ?? 0);

onMounted(() => {
    if (seconds.value <= 0) return;
    const timer = setInterval(() => {
        seconds.value -= 1;
        if (seconds.value <= 0) clearInterval(timer);
    }, 1000);
});

const waitLabel = computed(() => {
    if (seconds.value <= 0) return null;
    if (seconds.value < 60) return `${seconds.value}s`;
    return `${Math.ceil(seconds.value / 60)} min`;
});

function reload(): void {
    router.reload();
}

function goBack(): void {
    // history.back() would re-enter the erroring URL if this page *is* the
    // history entry, so step past it when there is somewhere to step to.
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
}

async function copyReference(): Promise<void> {
    if (!props.reference) return;
    try {
        await navigator.clipboard.writeText(props.reference);
        toasts.success('Reference copied', {
            description: 'Include it when you contact support.',
        });
    } catch {
        toasts.error('Could not copy', {
            description: `Reference: ${props.reference}`,
        });
    }
}

const homeHref = computed(() => (props.authenticated ? '/dashboard' : '/'));
</script>

<template>
    <Head :title="`${status} — ${copy.title}`" />

    <div class="relative flex min-h-dvh flex-col bg-page">
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <div
                class="absolute -top-48 left-1/2 size-[34rem] -translate-x-1/2 rounded-full opacity-[0.12] blur-3xl dark:opacity-20"
                :style="{ background: 'var(--brand-primary)' }"
            />
        </div>

        <header class="relative px-4 py-6 sm:px-6">
            <Link :href="homeHref" class="inline-flex items-center gap-2.5">
                <img :src="brand.assets.favicon_svg" alt="" class="size-7" />
                <span class="font-display text-h4 font-bold text-strong">
                    {{ brand.name }}
                </span>
            </Link>
        </header>

        <main
            class="relative flex flex-1 items-center justify-center px-4 pb-20 sm:px-6"
        >
            <div class="w-full max-w-lg text-center">
                <!-- The status code is the fastest thing to recognise, so it
                     leads — but it is decorative to a screen reader, which gets
                     the heading instead. -->
                <p
                    class="font-display text-[5rem] leading-none font-bold tabular-nums opacity-[0.13] select-none sm:text-[7rem]"
                    :style="{ color: 'var(--brand-primary)' }"
                    aria-hidden="true"
                >
                    {{ status }}
                </p>

                <div
                    class="-mt-7 mb-5 inline-flex size-12 items-center justify-center rounded-[var(--radius-card)] border border-border bg-surface shadow-card"
                    :class="copy.tone"
                >
                    <component
                        :is="copy.icon"
                        class="size-6"
                        aria-hidden="true"
                    />
                </div>

                <h1 class="text-h1 font-bold text-balance text-strong">
                    {{ copy.title }}
                </h1>

                <p
                    class="mx-auto mt-3 max-w-md text-body-lg text-pretty text-muted"
                >
                    {{ body }}
                </p>

                <p v-if="waitLabel" class="mt-3 text-body text-muted">
                    You can try again in
                    <span class="font-semibold text-strong tabular-nums">{{
                        waitLabel
                    }}</span
                    >.
                </p>

                <div
                    class="mt-8 flex flex-wrap items-center justify-center gap-2"
                >
                    <Button
                        v-if="status === 401"
                        variant="brand"
                        size="lg"
                        @click="router.visit('/login')"
                    >
                        Sign in
                    </Button>

                    <Button
                        v-else-if="copy.retry"
                        variant="brand"
                        size="lg"
                        :disabled="seconds > 0"
                        @click="reload"
                    >
                        <RefreshCw class="size-4" />
                        Try again
                    </Button>

                    <Button variant="secondary" size="lg" @click="goBack">
                        <ArrowLeft class="size-4" />
                        Go back
                    </Button>

                    <Link :href="homeHref">
                        <Button variant="ghost" size="lg">
                            <Home class="size-4" />
                            {{ authenticated ? 'Dashboard' : 'Home' }}
                        </Button>
                    </Link>
                </div>

                <!-- Support reference -->
                <div
                    v-if="reference"
                    class="mt-9 rounded-[var(--radius-card)] border border-border bg-surface p-4 text-left shadow-card"
                >
                    <div
                        class="flex items-center gap-2 text-small font-medium text-strong"
                    >
                        <LifeBuoy
                            class="size-4 text-muted"
                            aria-hidden="true"
                        />
                        Need help?
                    </div>
                    <p class="mt-1 text-small text-muted">
                        Quote this reference and we can find exactly what
                        happened.
                    </p>
                    <button
                        type="button"
                        class="mt-2.5 flex w-full items-center justify-between gap-2 rounded-[var(--radius-control)] border border-border bg-surface-alt px-3 py-2 font-mono text-small text-body transition-colors hover:bg-surface-sunken"
                        @click="copyReference"
                    >
                        <span class="truncate">{{ reference }}</span>
                        <Copy
                            class="size-3.5 shrink-0 text-muted"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </div>
        </main>

        <ToastHost />
    </div>
</template>
