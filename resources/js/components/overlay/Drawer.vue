<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * A right-hand panel for create and edit forms.
 *
 * A drawer rather than a full page, because editing a record almost always
 * happens from a list and the list is the context: losing it to navigate, then
 * navigating back and losing your scroll position and filters, is the thing
 * CRM users complain about most.
 *
 * Accessibility is the whole reason this is a component rather than a div per
 * page. It is a modal dialog, so it traps focus, returns focus where it came
 * from, closes on Escape, and labels itself (§115).
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        /** Wider for forms with two columns of fields. */
        size?: 'md' | 'lg';
        /** Blocks closing while a request is in flight. */
        busy?: boolean;
    }>(),
    { size: 'md', busy: false },
);

const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);

/** Where focus was before the drawer opened, so it can be given back. */
let previouslyFocused: HTMLElement | null = null;

function close(): void {
    // A half-saved record is worse than a drawer that ignored one Escape.
    if (props.busy) {
        return;
    }

    emit('close');
}

/**
 * Keeps Tab inside the panel.
 *
 * Without this, tabbing past the last field moves into the page behind the
 * overlay, where everything is visually obscured and nothing can be clicked.
 */
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();

        return;
    }

    if (event.key !== 'Tab' || !panel.value) {
        return;
    }

    const focusable = panel.value.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            previouslyFocused = document.activeElement as HTMLElement | null;
            // The page behind must not scroll under the panel.
            document.body.style.overflow = 'hidden';
            await nextTick();
            // The heading rather than the first field: a screen reader needs to
            // hear what this dialog is before being dropped into an input.
            heading.value?.focus();

            return;
        }

        document.body.style.overflow = '';
        previouslyFocused?.focus();
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50">
                <div
                    class="absolute inset-0 bg-[var(--overlay)]"
                    @click="close"
                />
            </div>
        </Transition>

        <Transition
            enter-active-class="transition-transform duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="translate-x-full"
            leave-active-class="transition-transform duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="open"
                ref="panel"
                class="fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-surface shadow-lg"
                :class="size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-md'"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`${title}-heading`"
                @keydown="onKeydown"
            >
                <header
                    class="flex shrink-0 items-start gap-3 border-b border-border px-5 py-4"
                >
                    <div class="min-w-0 flex-1">
                        <h2
                            :id="`${title}-heading`"
                            ref="heading"
                            tabindex="-1"
                            class="text-[1.02rem] font-semibold text-strong outline-none"
                        >
                            {{ title }}
                        </h2>
                        <p
                            v-if="description"
                            class="mt-0.5 text-[0.85rem] text-muted"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-md p-1.5 text-muted transition-colors hover:bg-surface-alt hover:text-strong disabled:opacity-50"
                        :disabled="busy"
                        aria-label="Close"
                        @click="close"
                    >
                        <X class="size-4.5" />
                    </button>
                </header>

                <!-- The form body scrolls; the header and footer do not, so the
                     save button is reachable without scrolling a long form. -->
                <div
                    class="min-h-0 flex-1 scrollbar-thin overflow-y-auto px-5 py-4"
                >
                    <slot />
                </div>

                <footer
                    v-if="$slots.footer"
                    class="flex shrink-0 items-center gap-2 border-t border-border bg-surface-alt px-5 py-3"
                >
                    <slot name="footer" />
                </footer>
            </div>
        </Transition>
    </Teleport>
</template>
