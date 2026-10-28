<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, KeyRound, LogOut, Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Avatar from '@/components/ui/Avatar.vue';
import { useThemeStore } from '@/stores/theme';
import { useUiStore } from '@/stores/ui';
import type { Theme } from '@/types/app';

const emit = defineEmits<{ notifications: [] }>();

const page = usePage();
const theme = useThemeStore();
const ui = useUiStore();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const user = computed(() => page.props.auth?.user ?? null);

const presence = computed(() => String(user.value?.presence ?? 'active'));

const presenceOptions = [
    { value: 'active', label: 'Active', dot: 'bg-success' },
    { value: 'away', label: 'Away', dot: 'bg-warning' },
    { value: 'busy', label: 'Busy', dot: 'bg-danger' },
] as const;

const themes: { value: Theme; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

const dot = computed(
    () => presenceOptions.find((option) => option.value === presence.value)?.dot ?? 'bg-success',
);

const shortcut = computed(() =>
    typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.platform)
        ? '⌘K'
        : '?',
);

function setPresence(value: string): void {
    if (!user.value || value === presence.value) {
        return;
    }

    router.patch('/account/presence', { presence: value }, { preserveScroll: true, preserveState: true });
}

function showNotifications(): void {
    open.value = false;
    emit('notifications');
}

function showShortcuts(): void {
    open.value = false;
    ui.openCommandPalette();
}

function signOut(): void {
    open.value = false;
    router.post('/logout');
}

function onPointerDown(event: PointerEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div v-if="user" ref="root" class="relative">
        <button
            type="button"
            class="relative ml-1 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2"
            :aria-label="`Account menu for ${user.name}`"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Avatar :name="user.name" :src="user.avatar" size="sm" />
            <span
                class="absolute right-0 bottom-0 size-2.5 rounded-full ring-2 ring-surface"
                :class="dot"
                aria-hidden="true"
            />
        </button>

        <div
            v-if="open"
            role="menu"
            class="absolute right-0 z-40 mt-2 w-72 overflow-hidden rounded-xl border border-border bg-surface shadow-card"
        >
            <div class="flex items-center gap-3 px-3 py-3">
                <span class="relative">
                    <Avatar :name="user.name" :src="user.avatar" size="md" />
                    <span
                        class="absolute right-0 bottom-0 size-2.5 rounded-full ring-2 ring-surface"
                        :class="dot"
                        aria-hidden="true"
                    />
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[0.95rem] font-semibold text-strong">{{ user.name }}</span>
                    <span class="block truncate text-[0.8rem] text-muted">{{ user.email }}</span>
                </span>
            </div>

            <div class="flex gap-1 px-3 pb-3" role="group" aria-label="Presence">
                <button
                    v-for="option in presenceOptions"
                    :key="option.value"
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border px-2 py-1.5 text-[0.8rem] font-medium transition-colors"
                    :class="
                        presence === option.value
                            ? 'border-primary-600 bg-primary-600 text-white'
                            : 'border-border text-body hover:bg-surface-alt'
                    "
                    :aria-pressed="presence === option.value"
                    @click="setPresence(option.value)"
                >
                    <span class="size-2 rounded-full" :class="option.dot" aria-hidden="true" />
                    {{ option.label }}
                </button>
            </div>

            <div class="border-t border-border py-1">
                <button
                    type="button"
                    role="menuitem"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[0.9rem] text-body hover:bg-surface-alt hover:text-strong"
                    @click="showNotifications"
                >
                    <Bell class="size-4 text-soft" aria-hidden="true" />
                    Notifications
                </button>
                <button
                    type="button"
                    role="menuitem"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[0.9rem] text-body hover:bg-surface-alt hover:text-strong"
                    @click="showShortcuts"
                >
                    <KeyRound class="size-4 text-soft" aria-hidden="true" />
                    Keyboard shortcuts
                    <span class="ml-auto rounded border border-border px-1.5 py-0.5 text-[0.7rem] text-muted">{{ shortcut }}</span>
                </button>
            </div>

            <div class="flex gap-1 border-t border-border px-3 py-2" role="group" aria-label="Theme">
                <button
                    v-for="option in themes"
                    :key="option.value"
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-[0.8rem] font-medium transition-colors"
                    :class="
                        theme.preference === option.value
                            ? 'bg-primary-600 text-white'
                            : 'text-body hover:bg-surface-alt'
                    "
                    :aria-pressed="theme.preference === option.value"
                    @click="theme.set(option.value)"
                >
                    <component :is="option.icon" class="size-3.5" aria-hidden="true" />
                    {{ option.label }}
                </button>
            </div>

            <div class="border-t border-border py-1">
                <button
                    type="button"
                    role="menuitem"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[0.9rem] text-body hover:bg-surface-alt hover:text-strong"
                    @click="signOut"
                >
                    <LogOut class="size-4 text-soft" aria-hidden="true" />
                    Sign out
                </button>
            </div>
        </div>
    </div>
</template>
