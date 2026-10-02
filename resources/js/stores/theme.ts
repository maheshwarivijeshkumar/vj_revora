import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { Theme } from '@/types/app';

const STORAGE_KEY = 'theme';

/**
 * Reads the stored preference defensively.
 *
 * localStorage throws in private mode and when site data is blocked, so every
 * access is guarded — a storage failure must degrade to the light theme, not
 * break the shell.
 */
function readStored(): Theme {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        if (value === 'light' || value === 'dark' || value === 'system') {
            return value;
        }
    } catch {
        /* Storage unavailable. */
    }
    return 'system';
}

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Theme preference: Light / Dark / System (§39, §123).
 *
 * The initial class is applied by the inline script in app.blade.php before
 * first paint; this store keeps it in sync afterwards.
 */
export const useThemeStore = defineStore('theme', () => {
    const preference = ref<Theme>(readStored());
    const systemDark = ref(systemPrefersDark());

    const isDark = computed(
        () =>
            preference.value === 'dark' ||
            (preference.value === 'system' && systemDark.value),
    );

    function apply(): void {
        document.documentElement.classList.toggle('dark', isDark.value);
    }

    function set(value: Theme): void {
        preference.value = value;

        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            /* Preference will not survive reload; the UI still updates. */
        }

        apply();
    }

    function toggle(): void {
        set(isDark.value ? 'light' : 'dark');
    }

    function init(): void {
        const query = window.matchMedia('(prefers-color-scheme: dark)');

        // Keeps "System" honest when the OS theme changes mid-session.
        query.addEventListener('change', (event) => {
            systemDark.value = event.matches;
            if (preference.value === 'system') {
                apply();
            }
        });

        apply();
    }

    return { preference, isDark, set, toggle, init };
});
