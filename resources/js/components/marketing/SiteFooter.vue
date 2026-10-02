<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const columns = [
    {
        title: 'Product',
        links: [
            { label: 'Lead capture', href: '/product#capture' },
            { label: 'AI qualification', href: '/product#qualify' },
            { label: 'Omnichannel inbox', href: '/product#engage' },
            { label: 'Automation', href: '/product#automate' },
            { label: 'Attribution', href: '/product#attribute' },
        ],
    },
    {
        title: 'Platform',
        links: [
            { label: 'Integrations', href: '/integrations' },
            { label: 'Security', href: '/security' },
            { label: 'Developers', href: '/developers' },
            { label: 'Pricing', href: '/pricing' },
        ],
    },
    {
        title: 'Company',
        links: [
            { label: 'Solutions', href: '/solutions' },
            { label: 'About', href: '/about' },
            { label: 'Contact', href: '/contact' },
            { label: 'Sign in', href: '/login' },
        ],
    },
];

const SOCIAL_LABELS: Record<string, string> = {
    linkedin: 'LinkedIn',
    youtube: 'YouTube',
    instagram: 'Instagram',
    x: 'X',
};

const socialLinks = computed(() =>
    Object.entries(props.social).map(([key, href]) => ({
        label: SOCIAL_LABELS[key] ?? key,
        href,
    })),
);

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-border">
        <div class="mx-auto max-w-[78rem] px-5 py-16 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-[22rem_1fr]">
                <div>
                    <Link href="/" class="flex items-center gap-2.5">
                        <img
                            :src="brand.assets.favicon_svg"
                            alt=""
                            class="size-7"
                        />
                        <span
                            class="font-display text-[1.15rem] font-bold tracking-[-0.02em] text-strong"
                        >
                            {{ brand.name }}
                        </span>
                    </Link>
                    <p
                        class="mt-4 max-w-[26ch] text-[0.9rem] leading-relaxed text-muted"
                    >
                        Lead generation, CRM and sales automation on one record,
                        from first click to closed revenue.
                    </p>

                    <dl
                        v-if="contact.email || contact.phone"
                        class="mt-6 space-y-1.5"
                    >
                        <div v-if="contact.email" class="text-[0.9rem]">
                            <dt class="sr-only">Email</dt>
                            <dd>
                                <a
                                    :href="`mailto:${contact.email}`"
                                    class="text-body underline decoration-border underline-offset-4 transition-colors hover:text-strong"
                                >
                                    {{ contact.email }}
                                </a>
                            </dd>
                        </div>
                        <div v-if="contact.phone" class="text-[0.9rem]">
                            <dt class="sr-only">Phone</dt>
                            <dd>
                                <a
                                    :href="`tel:${contact.phone}`"
                                    class="text-body underline decoration-border underline-offset-4 transition-colors hover:text-strong"
                                >
                                    {{ contact.phone }}
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="grid gap-10 sm:grid-cols-3">
                    <div v-for="column in columns" :key="column.title">
                        <h2
                            class="text-[0.7rem] font-semibold tracking-[0.1em] text-soft uppercase"
                        >
                            {{ column.title }}
                        </h2>
                        <ul class="mt-4 space-y-2.5">
                            <li v-for="link in column.links" :key="link.label">
                                <Link
                                    :href="link.href"
                                    class="text-[0.9rem] text-muted transition-colors hover:text-strong"
                                >
                                    {{ link.label }}
                                </Link>
                            </li>
                            <li
                                v-if="column.title === 'Company'"
                                v-for="s in socialLinks"
                                :key="s.label"
                            >
                                <a
                                    :href="s.href"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-[0.9rem] text-muted transition-colors hover:text-strong"
                                >
                                    {{ s.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div
                class="mt-14 flex flex-col gap-2 border-t border-border pt-6 text-[0.82rem] text-soft sm:flex-row sm:items-center sm:justify-between"
            >
                <p>&copy; {{ year }} {{ brand.name }}</p>
                <p>
                    All integrations run on official, authorized provider APIs.
                </p>
            </div>
        </div>
    </footer>
</template>
