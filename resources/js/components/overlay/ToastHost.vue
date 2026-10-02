<script setup lang="ts">
import {
    CircleAlert,
    CircleCheck,
    CircleX,
    Info,
    Loader2,
    X,
} from 'lucide-vue-next';
import { useToastStore } from '@/stores/toast';
import type { Toast, ToastAction, ToastType } from '@/types/app';

const toasts = useToastStore();

/**
 * Runs one of a toast's actions, then dismisses it. A toast that lingers
 * after its own button has been pressed looks broken.
 */
function runAction(toast: Toast, action?: ToastAction): void {
    action?.handler();
    toasts.dismiss(toast.id);
}

const ICONS: Record<ToastType, typeof Info> = {
    success: CircleCheck,
    info: Info,
    warning: CircleAlert,
    error: CircleX,
    loading: Loader2,
};

const TONES: Record<ToastType, string> = {
    success: 'text-emerald-600 dark:text-emerald-400',
    info: 'text-sky-600 dark:text-sky-400',
    warning: 'text-amber-600 dark:text-amber-400',
    error: 'text-red-600 dark:text-red-400',
    loading: 'text-muted',
};
</script>

<template>
    <!--
      aria-live="polite" so screen readers announce toasts without interrupting
      the current task (§117). Bottom-anchored on mobile where the top edge is
      occupied by system chrome.
    -->
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:top-0 sm:right-0 sm:bottom-auto sm:items-end"
        role="region"
        aria-live="polite"
        aria-label="Notifications"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 sm:translate-x-2 sm:translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts.toasts"
                :key="toast.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-[var(--radius-card)] border border-border bg-surface p-3.5 shadow-pop"
            >
                <component
                    :is="ICONS[toast.type]"
                    :class="[
                        'mt-0.5 size-4.5 shrink-0',
                        TONES[toast.type],
                        toast.type === 'loading' && 'animate-spin',
                    ]"
                    aria-hidden="true"
                />

                <div class="min-w-0 flex-1">
                    <p class="font-medium text-body text-strong">
                        {{ toast.title }}
                    </p>
                    <p
                        v-if="toast.description"
                        class="mt-0.5 text-small text-muted"
                    >
                        {{ toast.description }}
                    </p>
                    <div
                        v-if="toast.action"
                        class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5"
                    >
                        <button
                            type="button"
                            class="text-small font-semibold text-primary-600 hover:underline"
                            @click="runAction(toast, toast.action)"
                        >
                            {{ toast.action.label }}
                        </button>
                        <button
                            v-if="toast.secondaryAction"
                            type="button"
                            class="text-small font-medium text-muted hover:text-strong"
                            @click="runAction(toast, toast.secondaryAction)"
                        >
                            {{ toast.secondaryAction.label }}
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="shrink-0 rounded p-0.5 text-soft transition-colors hover:text-strong"
                    aria-label="Dismiss notification"
                    @click="toasts.dismiss(toast.id)"
                >
                    <X class="size-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
