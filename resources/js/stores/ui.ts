import { defineStore } from 'pinia';
import { ref } from 'vue';

const SIDEBAR_KEY = 'sidebar-collapsed';
const SUBNAV_KEY = 'subnav-collapsed';

function readFlag(key: string): boolean {
    try {
        return localStorage.getItem(key) === '1';
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
    const sidebarCollapsed = ref(readFlag(SIDEBAR_KEY));
    const subnavCollapsed = ref(readFlag(SUBNAV_KEY));
    const mobileNavOpen = ref(false);
    const commandPaletteOpen = ref(false);

    function persist(key: string, value: boolean): void {
        try {
            localStorage.setItem(key, value ? '1' : '0');
        } catch {
            /* Preference will not persist; the UI still responds. */
        }
    }

    function toggleSidebar(): void {
        sidebarCollapsed.value = !sidebarCollapsed.value;
        persist(SIDEBAR_KEY, sidebarCollapsed.value);
    }

    function toggleSubnav(): void {
        subnavCollapsed.value = !subnavCollapsed.value;
        persist(SUBNAV_KEY, subnavCollapsed.value);
    }

    return {
        sidebarCollapsed,
        subnavCollapsed,
        mobileNavOpen,
        commandPaletteOpen,
        toggleSidebar,
        toggleSubnav,
        openMobileNav: () => (mobileNavOpen.value = true),
        closeMobileNav: () => (mobileNavOpen.value = false),
        openCommandPalette: () => (commandPaletteOpen.value = true),
        closeCommandPalette: () => (commandPaletteOpen.value = false),
    };
});
