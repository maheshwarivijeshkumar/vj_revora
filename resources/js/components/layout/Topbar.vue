<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleHelp, Menu, PanelLeftOpen, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AccountMenu from '@/components/layout/AccountMenu.vue';
import NotificationMenu from '@/components/layout/NotificationMenu.vue';
import Button from '@/components/ui/Button.vue';
import { useUiStore } from '@/stores/ui';

const page = usePage();
const ui = useUiStore();
const notificationsOpen = ref(false);

/** Renders ⌘K on Apple platforms and Ctrl+K elsewhere (§120). */
const shortcut = computed(() =>
    typeof navigator !== 'undefined' &&
    /Mac|iPod|iPhone|iPad/.test(navigator.platform)
        ? '⌘K'
        : 'Ctrl K',
);
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-2 border-b border-border bg-surface px-3 sm:px-4"
    >
        <Button
            variant="ghost"
            size="icon"
            class="lg:hidden"
            aria-label="Open navigation"
            @click="ui.openMobileNav()"
        >
            <Menu class="size-5" />
        </Button>

        <Button
            v-if="ui.subnavCollapsed"
            variant="ghost"
            size="icon"
            class="hidden lg:inline-flex"
            aria-label="Show section menu"
            @click="ui.toggleSubnav()"
        >
            <PanelLeftOpen class="size-4.5" />
        </Button>

        <div class="hidden min-w-0 items-center gap-2 lg:flex">
            <img src="/brand/revora/favicon.svg" alt="" class="size-6 shrink-0" />
            <span class="truncate text-[0.95rem] font-semibold text-strong">
                {{ page.props.name }}
            </span>
        </div>

        <!-- Global search doubles as the command palette trigger (§45, §120) -->
        <button
            type="button"
            class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-[var(--radius-control)] border border-border bg-surface-alt px-3 text-left text-body text-muted transition-colors hover:bg-surface-sunken sm:mx-auto sm:max-w-md"
            @click="ui.openCommandPalette()"
        >
            <Search class="size-4 shrink-0" aria-hidden="true" />
            <span class="truncate">Search or jump to…</span>
            <kbd
                class="ml-auto hidden shrink-0 rounded border border-border bg-surface px-1.5 py-0.5 text-caption font-medium sm:block"
            >
                {{ shortcut }}
            </kbd>
        </button>

        <div class="ml-auto flex items-center gap-1">
            <Button variant="ghost" size="icon" aria-label="Help">
                <CircleHelp class="size-4.5" />
            </Button>

            <NotificationMenu v-model:request-open="notificationsOpen" />

            <AccountMenu @notifications="notificationsOpen = true" />
        </div>
    </header>
</template>
