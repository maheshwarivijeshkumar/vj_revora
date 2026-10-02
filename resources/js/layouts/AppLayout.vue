<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, X } from 'lucide-vue-next';
import { onMounted, onUnmounted, watch } from 'vue';
import Sidebar from '@/components/layout/Sidebar.vue';
import Topbar from '@/components/layout/Topbar.vue';
import CommandPalette from '@/components/overlay/CommandPalette.vue';
import ToastHost from '@/components/overlay/ToastHost.vue';
import { useThemeStore } from '@/stores/theme';
import { useToastStore } from '@/stores/toast';
import { useUiStore } from '@/stores/ui';
import type { BreadcrumbItem } from '@/types/app';

withDefaults(
    defineProps<{ title?: string; breadcrumbs?: BreadcrumbItem[] }>(),
    { breadcrumbs: () => [] },
);

const page = usePage();
const theme = useThemeStore();
const toasts = useToastStore();
const ui = useUiStore();

function onKeydown(event: KeyboardEvent): void {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        ui.openCommandPalette();
    }
    if (event.key === 'Escape') {
        ui.closeCommandPalette();
        ui.closeMobileNav();
    }
}

onMounted(() => {
    theme.init();
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => window.removeEventListener('keydown', onKeydown));

// Server-side flash messages surface through the same toast service the
// client uses, so feedback looks identical whatever produced it (§117).
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        const f = flash as Record<string, string | null>;
        if (f.success) toasts.success(f.success);
        if (f.error) toasts.error(f.error);
        if (f.warning) toasts.warning(f.warning);
        if (f.info) toasts.info(f.info);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="flex h-dvh overflow-hidden bg-page">
        <!-- Desktop sidebar -->
        <div class="hidden lg:block">
            <Sidebar />
        </div>

        <!-- Mobile drawer (§71, §122) -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="ui.mobileNavOpen"
                class="fixed inset-0 z-40 bg-[var(--overlay)] lg:hidden"
                @click="ui.closeMobileNav()"
            />
        </Transition>

        <Transition
            enter-active-class="transition-transform duration-200 ease-out"
            enter-from-class="-translate-x-full"
            leave-active-class="transition-transform duration-150 ease-in"
            leave-to-class="-translate-x-full"
        >
            <div
                v-if="ui.mobileNavOpen"
                class="fixed inset-y-0 left-0 z-50 lg:hidden"
            >
                <Sidebar />
                <button
                    type="button"
                    class="absolute top-3.5 -right-11 rounded-[var(--radius-control)] bg-surface p-2 text-muted shadow-card"
                    aria-label="Close navigation"
                    @click="ui.closeMobileNav()"
                >
                    <X class="size-4.5" />
                </button>
            </div>
        </Transition>

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar />

            <main class="flex-1 scrollbar-thin overflow-y-auto">
                <div
                    v-if="title || breadcrumbs.length || $slots.actions"
                    class="border-b border-border bg-surface px-4 py-4 sm:px-6"
                >
                    <nav
                        v-if="breadcrumbs.length"
                        class="mb-1 flex items-center gap-1 text-small text-muted"
                        aria-label="Breadcrumb"
                    >
                        <template v-for="(crumb, i) in breadcrumbs" :key="i">
                            <ChevronRight
                                v-if="i > 0"
                                class="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <Link
                                v-if="crumb.href"
                                :href="crumb.href"
                                class="transition-colors hover:text-strong"
                            >
                                {{ crumb.label }}
                            </Link>
                            <span v-else class="text-strong">{{
                                crumb.label
                            }}</span>
                        </template>
                    </nav>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h1 v-if="title" class="text-h2 text-strong">
                            {{ title }}
                        </h1>
                        <div class="flex items-center gap-2">
                            <slot name="actions" />
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <slot />
                </div>
            </main>
        </div>

        <CommandPalette />
        <ToastHost />
    </div>
</template>
