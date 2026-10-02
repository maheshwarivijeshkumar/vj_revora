<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { resolveIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';
import { tenantNavigation } from '@/lib/navigation';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const page = usePage();
const { can, hasFeature } = useAuthorization();

/**
 * Sections filtered by permission *and* plan entitlement, then dropped
 * entirely when empty — so a section header never sits above nothing.
 */
const sections = computed(() =>
    tenantNavigation
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) =>
                    (!item.permission || can(item.permission)) &&
                    (!item.feature || hasFeature(item.feature)),
            ),
        }))
        .filter((section) => section.items.length > 0),
);

function isActive(href?: string): boolean {
    if (!href) return false;
    const current = page.url.split('?')[0];
    return current === href || current.startsWith(`${href}/`);
}
</script>

<template>
    <aside
        :class="
            cn(
                'flex h-full flex-col border-r border-border bg-sidebar transition-[width] duration-200',
                ui.sidebarCollapsed ? 'w-16' : 'w-64',
            )
        "
    >
        <div class="flex h-14 items-center gap-2.5 border-b border-border px-4">
            <img
                src="/brand/revora/favicon.svg"
                alt=""
                class="size-7 shrink-0"
            />
            <span
                v-if="!ui.sidebarCollapsed"
                class="truncate font-display text-h4 font-bold text-strong"
            >
                {{ page.props.name }}
            </span>
        </div>

        <nav
            class="flex-1 scrollbar-thin space-y-5 overflow-y-auto px-2.5 py-4"
        >
            <div v-for="(section, i) in sections" :key="i">
                <p
                    v-if="section.label && !ui.sidebarCollapsed"
                    class="px-2.5 pb-1.5 text-caption font-semibold tracking-wide text-soft uppercase"
                >
                    {{ section.label }}
                </p>

                <ul class="space-y-0.5">
                    <li v-for="item in section.items" :key="item.label">
                        <Link
                            :href="item.href ?? '#'"
                            :class="
                                cn(
                                    'flex items-center gap-3 rounded-[var(--radius-control)] px-2.5 py-2',
                                    'text-body transition-colors duration-150',
                                    isActive(item.href)
                                        ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'
                                        : 'text-body hover:bg-sidebar-hover hover:text-strong',
                                    ui.sidebarCollapsed &&
                                        'justify-center px-0',
                                )
                            "
                            :aria-current="
                                isActive(item.href) ? 'page' : undefined
                            "
                            :title="
                                ui.sidebarCollapsed ? item.label : undefined
                            "
                        >
                            <component
                                :is="resolveIcon(item.icon)"
                                class="size-4.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span
                                v-if="!ui.sidebarCollapsed"
                                class="truncate"
                                >{{ item.label }}</span
                            >
                            <span
                                v-if="item.badge && !ui.sidebarCollapsed"
                                class="ml-auto rounded-[var(--radius-pill)] bg-primary-600 px-1.5 py-0.5 text-caption font-medium text-white"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>

        <button
            type="button"
            class="flex h-11 items-center justify-center border-t border-border text-muted transition-colors hover:bg-sidebar-hover hover:text-strong"
            :aria-label="
                ui.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'
            "
            @click="ui.toggleSidebar()"
        >
            <component
                :is="ui.sidebarCollapsed ? PanelLeftOpen : PanelLeftClose"
                class="size-4.5"
                aria-hidden="true"
            />
        </button>
    </aside>
</template>
