<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { PanelLeftClose } from 'lucide-vue-next';
import { computed } from 'vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { resolveIcon } from '@/lib/icons';
import { tenantAreas } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/ui';
import type { NavArea, NavItem, NavSection } from '@/types/app';

const props = withDefaults(defineProps<{ variant?: 'desktop' | 'drawer' }>(), {
    variant: 'desktop',
});

const ui = useUiStore();
const page = usePage();
const { can, hasFeature } = useAuthorization();

const path = computed(() => page.url.split('?')[0]);

function allowed(item: NavItem): boolean {
    return (
        (!item.permission || can(item.permission)) &&
        (!item.feature || hasFeature(item.feature))
    );
}

function visibleSections(area: NavArea): NavSection[] {
    return area.sections
        .map((section) => ({
            ...section,
            items: section.items.filter(allowed),
        }))
        .filter((section) => section.items.length > 0);
}

const areas = computed(() =>
    tenantAreas
        .map((area) => ({ ...area, sections: visibleSections(area) }))
        .filter((area) => area.sections.length > 0),
);

function itemActive(href?: string): boolean {
    if (!href) {
        return false;
    }

    return path.value === href || path.value.startsWith(`${href}/`);
}

function areaActive(area: NavArea): boolean {
    return area.sections.some((section) =>
        section.items.some((item) => itemActive(item.href)),
    );
}

const current = computed(
    () => areas.value.find((area) => areaActive(area)) ?? areas.value[0],
);

const showMenu = computed(
    () => props.variant === 'drawer' || !ui.subnavCollapsed,
);

function firstHref(area: NavArea): string {
    return area.sections[0]?.items[0]?.href ?? '/dashboard';
}

function openArea(): void {
    if (props.variant === 'drawer') {
        ui.closeMobileNav();
    }
}
</script>

<template>
    <div class="flex h-full bg-sidebar">
        <nav
            class="flex w-14 shrink-0 flex-col border-r border-border py-3"
            aria-label="Primary"
        >
            <ul class="flex flex-1 flex-col items-center gap-1">
                <li
                    v-for="area in areas.filter((item) => item.id !== 'settings')"
                    :key="area.id"
                >
                    <Link
                        :href="firstHref(area)"
                        :title="area.label"
                        :aria-label="area.label"
                        :aria-current="areaActive(area) ? 'page' : undefined"
                        :class="
                            cn(
                                'flex size-10 items-center justify-center rounded-[var(--radius-control)] transition-colors',
                                areaActive(area)
                                    ? 'bg-primary-600 text-white'
                                    : 'text-muted hover:bg-sidebar-hover hover:text-strong',
                            )
                        "
                        @click="openArea"
                    >
                        <component
                            :is="resolveIcon(area.icon)"
                            class="size-4.5"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>

            <ul class="flex flex-col items-center gap-1 border-t border-border pt-2">
                <li v-for="area in areas.filter((item) => item.id === 'settings')" :key="area.id">
                    <Link
                        :href="firstHref(area)"
                        :title="area.label"
                        :aria-label="area.label"
                        :aria-current="areaActive(area) ? 'page' : undefined"
                        :class="
                            cn(
                                'flex size-10 items-center justify-center rounded-[var(--radius-control)] transition-colors',
                                areaActive(area)
                                    ? 'bg-primary-600 text-white'
                                    : 'text-muted hover:bg-sidebar-hover hover:text-strong',
                            )
                        "
                        @click="openArea"
                    >
                        <component
                            :is="resolveIcon(area.icon)"
                            class="size-4.5"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </nav>

        <aside
            v-show="showMenu && current"
            class="flex w-60 shrink-0 flex-col border-r border-border"
        >
            <div class="flex h-14 items-center justify-between gap-2 border-b border-border px-3">
                <p class="truncate text-[0.95rem] font-semibold text-strong">
                    {{ current?.label }}
                </p>
                <button
                    v-if="variant === 'desktop'"
                    type="button"
                    class="rounded-[var(--radius-control)] p-1.5 text-muted transition-colors hover:bg-sidebar-hover hover:text-strong"
                    aria-label="Collapse menu"
                    @click="ui.toggleSubnav()"
                >
                    <PanelLeftClose class="size-4" aria-hidden="true" />
                </button>
            </div>

            <nav class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-2.5 py-4" :aria-label="current?.label">
                <div v-for="(section, index) in current?.sections ?? []" :key="section.label ?? index">
                    <p
                        v-if="section.label && current && current.sections.length > 1"
                        class="px-2.5 pb-1.5 text-caption font-semibold tracking-wide text-soft uppercase"
                    >
                        {{ section.label }}
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="item in section.items" :key="item.href">
                            <Link
                                :href="item.href ?? '#'"
                                :class="
                                    cn(
                                        'flex items-center gap-2.5 rounded-[var(--radius-control)] px-2.5 py-2 text-[0.875rem] transition-colors',
                                        itemActive(item.href)
                                            ? 'bg-primary-600 font-medium text-white'
                                            : 'text-body hover:bg-sidebar-hover hover:text-strong',
                                    )
                                "
                                :aria-current="itemActive(item.href) ? 'page' : undefined"
                                @click="openArea"
                            >
                                <component
                                    :is="resolveIcon(item.icon)"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>
    </div>
</template>
