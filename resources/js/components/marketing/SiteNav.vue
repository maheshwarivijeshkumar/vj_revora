<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu, Moon, Sun, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useThemeStore } from '@/stores/theme';

defineProps<{ brand: { name: string; assets: Record<string, string> } }>();

const page = usePage();
const theme = useThemeStore();

const mobileOpen = ref(false);
const productOpen = ref(false);
const scrolled = ref(false);

let closeTimer: ReturnType<typeof setTimeout> | undefined;

const productLinks = [
    {
        label: 'Lead capture',
        href: '/product#capture',
        note: 'Authorized sources, one record',
    },
    {
        label: 'AI qualification',
        href: '/product#qualify',
        note: 'Scoring that shows its working',
    },
    {
        label: 'Omnichannel inbox',
        href: '/product#engage',
        note: 'WhatsApp, email and SMS',
    },
    {
        label: 'Automation',
        href: '/product#automate',
        note: 'Manual, approval, autonomous',
    },
    {
        label: 'Attribution',
        href: '/product#attribute',
        note: 'Revenue back to source',
    },
];

const links = [
    { label: 'Solutions', href: '/solutions' },
    { label: 'Integrations', href: '/integrations' },
    { label: 'Security', href: '/security' },
    { label: 'Developers', href: '/developers' },
    { label: 'Pricing', href: '/pricing' },
];

const current = computed(() => page.url.split('?')[0]);

function isActive(href: string): boolean {
    return current.value === href || current.value.startsWith(`${href}/`);
}

function onScroll(): void {
    scrolled.value = window.scrollY > 4;
}

// A short close delay keeps the dropdown usable: without it the menu vanishes
// while the pointer crosses the gap between trigger and panel.
function openProduct(): void {
    clearTimeout(closeTimer);
    productOpen.value = true;
}

function closeProduct(): void {
    closeTimer = setTimeout(() => (productOpen.value = false), 120);
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    clearTimeout(closeTimer);
});
</script>

<template>
    <header
        class="sticky top-0 z-50 border-b transition-colors duration-200"
        :class="
            scrolled
                ? 'border-border bg-surface/90 backdrop-blur-xl'
                : 'border-transparent bg-transparent'
        "
    >
        <div class="mx-auto max-w-[78rem] px-5 lg:px-8">
            <nav class="flex h-[4.25rem] items-center" aria-label="Main">
                <Link href="/" class="flex shrink-0 items-center gap-2.5">
                    <img
                        :src="brand.assets.favicon_svg"
                        alt=""
                        class="size-[1.85rem]"
                    />
                    <span
                        class="font-display text-[1.3rem] leading-none font-bold tracking-[-0.02em] text-strong"
                    >
                        {{ brand.name }}
                    </span>
                </Link>

                <ul class="ml-10 hidden items-center gap-0.5 lg:flex">
                    <li
                        class="relative"
                        @mouseenter="openProduct"
                        @mouseleave="closeProduct"
                    >
                        <Link
                            href="/product"
                            class="flex items-center gap-1 rounded-md px-3 py-2 text-[0.9rem] transition-colors"
                            :class="
                                isActive('/product')
                                    ? 'text-strong'
                                    : 'text-muted hover:text-strong'
                            "
                        >
                            Product
                            <ChevronDown
                                class="size-3.5 transition-transform duration-150"
                                :class="productOpen && 'rotate-180'"
                                aria-hidden="true"
                            />
                        </Link>

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="translate-y-1 opacity-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="productOpen"
                                class="absolute top-full -left-2 w-[22rem] pt-2"
                            >
                                <div
                                    class="overflow-hidden rounded-xl border border-border bg-surface p-1.5 shadow-modal"
                                >
                                    <Link
                                        v-for="item in productLinks"
                                        :key="item.href"
                                        :href="item.href"
                                        class="block rounded-lg px-3 py-2.5 transition-colors hover:bg-surface-alt"
                                    >
                                        <span
                                            class="block text-[0.9rem] font-medium text-strong"
                                        >
                                            {{ item.label }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-[0.8rem] text-muted"
                                        >
                                            {{ item.note }}
                                        </span>
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </li>

                    <li v-for="link in links" :key="link.href">
                        <Link
                            :href="link.href"
                            class="block rounded-md px-3 py-2 text-[0.9rem] transition-colors"
                            :class="
                                isActive(link.href)
                                    ? 'text-strong'
                                    : 'text-muted hover:text-strong'
                            "
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>

                <div class="ml-auto flex items-center gap-1.5">
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-alt hover:text-strong"
                        :aria-label="
                            theme.isDark ? 'Light theme' : 'Dark theme'
                        "
                        @click="theme.toggle()"
                    >
                        <component
                            :is="theme.isDark ? Sun : Moon"
                            class="size-[1.1rem]"
                        />
                    </button>

                    <Link
                        href="/login"
                        class="hidden rounded-md px-3 py-2 text-[0.9rem] text-muted transition-colors hover:text-strong sm:block"
                    >
                        Sign in
                    </Link>

                    <Link href="/contact" class="hidden sm:block">
                        <Button variant="brand" size="sm">Book a demo</Button>
                    </Link>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-md text-muted lg:hidden"
                        :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <component :is="mobileOpen ? X : Menu" class="size-5" />
                    </button>
                </div>
            </nav>
        </div>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileOpen"
                class="border-t border-border bg-surface lg:hidden"
            >
                <div
                    class="mx-auto max-w-[78rem] space-y-0.5 px-5 py-4 lg:px-8"
                >
                    <p
                        class="px-3 pt-1 pb-1.5 text-[0.7rem] font-semibold tracking-[0.1em] text-soft uppercase"
                    >
                        Product
                    </p>
                    <Link
                        v-for="item in productLinks"
                        :key="item.href"
                        :href="item.href"
                        class="block rounded-md px-3 py-2 text-[0.95rem] text-body"
                    >
                        {{ item.label }}
                    </Link>

                    <div class="my-2 h-px bg-border" />

                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="block rounded-md px-3 py-2 text-[0.95rem] text-body"
                    >
                        {{ link.label }}
                    </Link>
                    <Link
                        href="/login"
                        class="block rounded-md px-3 py-2 text-[0.95rem] text-body"
                    >
                        Sign in
                    </Link>
                </div>
            </div>
        </Transition>
    </header>
</template>
