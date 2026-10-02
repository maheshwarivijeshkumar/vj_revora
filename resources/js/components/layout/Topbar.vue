<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Bell,
    CircleHelp,
    Menu,
    Monitor,
    Moon,
    Plus,
    Search,
    Sun,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';
import type { Theme } from '@/types/app';

const page = usePage();
const theme = useThemeStore();
const ui = useUiStore();

const user = computed(() => page.props.auth?.user ?? null);

const THEMES: { value: Theme; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

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

        <!-- Global search doubles as the command palette trigger (§45, §120) -->
        <button
            type="button"
            class="flex h-9 flex-1 items-center gap-2 rounded-[var(--radius-control)] border border-border bg-surface-alt px-3 text-left text-body text-muted transition-colors hover:bg-surface-sunken sm:max-w-md"
            @click="ui.openCommandPalette()"
        >
            <Search class="size-4 shrink-0" aria-hidden="true" />
            <span class="truncate">Search leads, contacts, deals…</span>
            <kbd
                class="ml-auto hidden shrink-0 rounded border border-border bg-surface px-1.5 py-0.5 text-caption font-medium sm:block"
            >
                {{ shortcut }}
            </kbd>
        </button>

        <div class="ml-auto flex items-center gap-1">
            <Button variant="primary" size="sm" class="hidden sm:inline-flex">
                <Plus class="size-4" />
                Create
            </Button>

            <!-- Light / Dark / System (§39) -->
            <div
                class="hidden items-center rounded-[var(--radius-control)] border border-border bg-surface-alt p-0.5 sm:flex"
                role="group"
                aria-label="Theme"
            >
                <button
                    v-for="option in THEMES"
                    :key="option.value"
                    type="button"
                    class="flex size-7 items-center justify-center rounded-[6px] transition-colors"
                    :class="
                        theme.preference === option.value
                            ? 'bg-surface text-strong shadow-card'
                            : 'text-muted hover:text-strong'
                    "
                    :aria-label="option.label"
                    :aria-pressed="theme.preference === option.value"
                    @click="theme.set(option.value)"
                >
                    <component
                        :is="option.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <Button variant="ghost" size="icon" aria-label="Help">
                <CircleHelp class="size-4.5" />
            </Button>

            <Button variant="ghost" size="icon" aria-label="Notifications">
                <Bell class="size-4.5" />
            </Button>

            <button
                v-if="user"
                type="button"
                class="ml-1 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2"
                :aria-label="`Account menu for ${user.name}`"
            >
                <Avatar :name="user.name" :src="user.avatar" size="sm" />
            </button>
        </div>
    </header>
</template>
