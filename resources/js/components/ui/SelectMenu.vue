<script setup lang="ts">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

export type SelectOption = {
    value: string;
    label: string;
    /** Second line under the label, for disambiguating similar options. */
    note?: string;
    disabled?: boolean;
    /** Options sharing a group are rendered under one heading. */
    group?: string;
};

/**
 * Searchable select, in the spirit of Select2 but without the jQuery.
 *
 * Built on a native button + listbox rather than a styled `<select>`, because
 * a native select cannot show a search box, two-line options or group
 * headings. Keyboard behaviour follows the ARIA combobox pattern: arrows move
 * the active option, Enter commits, Escape closes, typing filters.
 *
 * Search only appears once the list is long enough to need it — a filter box
 * above five options is noise.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        options: SelectOption[];
        placeholder?: string;
        required?: boolean;
        invalid?: boolean;
        disabled?: boolean;
        hint?: boolean;
        clearable?: boolean;
        searchThreshold?: number;
        searchPlaceholder?: string;
    }>(),
    {
        placeholder: 'Select an option',
        required: false,
        invalid: false,
        disabled: false,
        hint: false,
        clearable: false,
        searchThreshold: 7,
        searchPlaceholder: 'Search options',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const open = ref(false);
const query = ref('');
const activeIndex = ref(-1);
const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const listbox = ref<HTMLElement | null>(null);

const selected = computed(() =>
    props.options.find((option) => option.value === props.modelValue),
);

const showSearch = computed(() => props.options.length >= props.searchThreshold);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.options;
    }

    return props.options.filter(
        (option) =>
            option.label.toLowerCase().includes(q) ||
            option.note?.toLowerCase().includes(q),
    );
});

/** Filtered options arranged under their group headings, order preserved. */
const grouped = computed(() => {
    const groups: { name: string | null; options: SelectOption[] }[] = [];

    for (const option of filtered.value) {
        const name = option.group ?? null;
        const last = groups.at(-1);

        if (last && last.name === name) {
            last.options.push(option);
        } else {
            groups.push({ name, options: [option] });
        }
    }

    return groups;
});

/** Flat index of an option within `filtered`, for roving focus. */
function indexOf(option: SelectOption): number {
    return filtered.value.indexOf(option);
}

const activeId = computed(() =>
    activeIndex.value >= 0 ? `${props.id}-option-${activeIndex.value}` : undefined,
);

const describedBy = computed(() => {
    const ids = [
        props.hint ? `${props.id}-hint` : null,
        props.invalid ? `${props.id}-error` : null,
    ];
    return ids.filter(Boolean).join(' ') || undefined;
});

async function openMenu(): Promise<void> {
    if (props.disabled) {
        return;
    }

    open.value = true;
    query.value = '';
    // Start on the current selection so Enter is a no-op rather than a
    // surprise change.
    activeIndex.value = selected.value ? props.options.indexOf(selected.value) : 0;

    await nextTick();
    searchInput.value?.focus();
    scrollActiveIntoView();
}

function closeMenu(): void {
    open.value = false;
    activeIndex.value = -1;
}

function choose(option: SelectOption): void {
    if (option.disabled) {
        return;
    }

    emit('update:modelValue', option.value);
    closeMenu();
    // Focus returns to the trigger, otherwise the tab order restarts from the
    // top of the document after a selection.
    (root.value?.querySelector('[data-trigger]') as HTMLElement | null)?.focus();
}

function clear(event: Event): void {
    event.stopPropagation();
    emit('update:modelValue', '');
}

function move(delta: number): void {
    const count = filtered.value.length;

    if (count === 0) {
        return;
    }

    let next = activeIndex.value;

    // Skip disabled entries rather than letting the cursor stall on them.
    for (let i = 0; i < count; i++) {
        next = (next + delta + count) % count;
        if (!filtered.value[next]?.disabled) {
            break;
        }
    }

    activeIndex.value = next;
    scrollActiveIntoView();
}

function scrollActiveIntoView(): void {
    void nextTick(() => {
        listbox.value
            ?.querySelector('[data-active="true"]')
            ?.scrollIntoView({ block: 'nearest' });
    });
}

function onTriggerKeydown(event: KeyboardEvent): void {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        void openMenu();
    }
}

function onMenuKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Home':
            event.preventDefault();
            activeIndex.value = 0;
            scrollActiveIntoView();
            break;
        case 'End':
            event.preventDefault();
            activeIndex.value = filtered.value.length - 1;
            scrollActiveIntoView();
            break;
        case 'Enter': {
            event.preventDefault();
            const option = filtered.value[activeIndex.value];
            if (option) {
                choose(option);
            }
            break;
        }
        case 'Escape':
            event.preventDefault();
            closeMenu();
            (root.value?.querySelector('[data-trigger]') as HTMLElement | null)?.focus();
            break;
        case 'Tab':
            closeMenu();
            break;
    }
}

function onPointerDown(event: PointerEvent): void {
    if (open.value && !root.value?.contains(event.target as Node)) {
        closeMenu();
    }
}

// Filtering can leave the cursor past the end of the list.
watch(filtered, (options) => {
    if (activeIndex.value >= options.length) {
        activeIndex.value = options.length > 0 ? 0 : -1;
    }
});

onMounted(() => document.addEventListener('pointerdown', onPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));
</script>

<template>
    <div ref="root" class="relative">
        <button
            :id="id"
            data-trigger
            type="button"
            role="combobox"
            :aria-expanded="open"
            :aria-controls="`${id}-listbox`"
            aria-haspopup="listbox"
            :aria-required="required || undefined"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            :disabled="disabled"
            :class="
                cn(
                    'flex h-11 w-full items-center gap-2 rounded-lg border bg-surface px-3.5 text-left',
                    'text-[0.95rem] transition-colors',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                    invalid
                        ? 'border-danger focus-visible:outline-danger'
                        : 'border-border hover:border-border-strong',
                )
            "
            @click="open ? closeMenu() : openMenu()"
            @keydown="onTriggerKeydown"
        >
            <span
                class="min-w-0 flex-1 truncate"
                :class="selected ? 'text-strong' : 'text-soft'"
            >
                {{ selected?.label ?? placeholder }}
            </span>

            <span
                v-if="clearable && selected && !disabled"
                role="button"
                tabindex="-1"
                aria-label="Clear selection"
                class="shrink-0 rounded p-0.5 text-soft transition-colors hover:text-strong"
                @click="clear"
            >
                <X class="size-3.5" />
            </span>

            <ChevronDown
                class="size-4 shrink-0 text-muted transition-transform duration-150"
                :class="open && 'rotate-180'"
                aria-hidden="true"
            />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="absolute z-30 mt-1.5 w-full overflow-hidden rounded-lg border border-border bg-surface shadow-modal"
                @keydown="onMenuKeydown"
            >
                <div v-if="showSearch" class="border-b border-border p-2">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-soft"
                            aria-hidden="true"
                        />
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="text"
                            :placeholder="searchPlaceholder"
                            :aria-label="searchPlaceholder"
                            autocomplete="off"
                            class="h-9 w-full rounded-md border border-border bg-surface-alt pr-3 pl-8 text-[0.9rem] text-strong placeholder:text-soft"
                        />
                    </div>
                </div>

                <ul
                    :id="`${id}-listbox`"
                    ref="listbox"
                    role="listbox"
                    :aria-activedescendant="activeId"
                    :tabindex="showSearch ? -1 : 0"
                    class="scrollbar-thin max-h-64 overflow-y-auto p-1.5"
                >
                    <li
                        v-if="filtered.length === 0"
                        class="px-3 py-6 text-center text-[0.9rem] text-muted"
                    >
                        Nothing matches “{{ query }}”.
                    </li>

                    <template v-for="(group, gi) in grouped" :key="gi">
                        <li
                            v-if="group.name"
                            role="presentation"
                            class="px-3 pt-3 pb-1.5 text-[0.7rem] font-semibold tracking-[0.1em] text-soft uppercase first:pt-1.5"
                        >
                            {{ group.name }}
                        </li>

                        <li
                            v-for="option in group.options"
                            :id="`${id}-option-${indexOf(option)}`"
                            :key="option.value"
                            role="option"
                            :aria-selected="option.value === modelValue"
                            :aria-disabled="option.disabled || undefined"
                            :data-active="indexOf(option) === activeIndex"
                            :class="
                                cn(
                                    'flex cursor-pointer items-start gap-2.5 rounded-md px-3 py-2 text-[0.92rem]',
                                    option.disabled && 'cursor-not-allowed opacity-45',
                                    indexOf(option) === activeIndex && 'bg-surface-alt',
                                )
                            "
                            @click="choose(option)"
                            @mousemove="activeIndex = indexOf(option)"
                        >
                            <Check
                                :class="
                                    cn(
                                        'mt-0.5 size-4 shrink-0 text-primary-600',
                                        option.value === modelValue
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )
                                "
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block truncate text-strong">
                                    {{ option.label }}
                                </span>
                                <span
                                    v-if="option.note"
                                    class="mt-0.5 block truncate text-[0.82rem] text-muted"
                                >
                                    {{ option.note }}
                                </span>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
        </Transition>
    </div>
</template>
