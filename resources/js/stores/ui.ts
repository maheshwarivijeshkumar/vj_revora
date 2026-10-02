import { defineStore } from 'pinia';
import { ref } from 'vue';

const SIDEBAR_KEY = 'sidebar-collapsed';

function readCollapsed(): boolean {
    try {
        return localStorage.getItem(SIDEBAR_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * Chrome state that must survive Inertia page navigations: sidebar collapse,
 * the mobile drawer and the command palette.
 *
 * Deliberately not server state — Inertia already delivers that.
 */
export const useUiStore = defineStore('ui', () => {
    const sidebarCollapsed = ref(readCollapsed());
    const mobileNavOpen = ref(false);
    const commandPaletteOpen = ref(false);

    function toggleSidebar(): void {
        sidebarCollapsed.value = !sidebarCollapsed.value;
        try {
            localStorage.setItem(
                SIDEBAR_KEY,
                sidebarCollapsed.value ? '1' : '0',
            );
        } catch {
            /* Preference will not persist; the UI still responds. */
        }
    }

    return {
        sidebarCollapsed,
        mobileNavOpen,
        commandPaletteOpen,
        toggleSidebar,
        openMobileNav: () => (mobileNavOpen.value = true),
        closeMobileNav: () => (mobileNavOpen.value = false),
        openCommandPalette: () => (commandPaletteOpen.value = true),
        closeCommandPalette: () => (commandPaletteOpen.value = false),
    };
});
