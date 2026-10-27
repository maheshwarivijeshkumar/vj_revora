import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Toast, ToastAction, ToastType } from '@/types/app';

type ToastOptions = {
    description?: string;
    duration?: number;
    action?: ToastAction;
    secondaryAction?: ToastAction;
};

/**
 * A unique id for one toast.
 *
 * Deliberately not `crypto.randomUUID()`: that only exists in a secure context,
 * so on a plain-HTTP development host like `http://myapp.test` it is undefined
 * and every single toast in the application throws instead of appearing. A
 * counter is enough — these ids live as long as a toast is on screen and are
 * never stored, compared across tabs or sent anywhere.
 */
let sequence = 0;

const nextId = (): string => `toast-${++sequence}`;

/** Auto-dismiss defaults. Errors persist until dismissed (§117). */
const DEFAULT_DURATION: Record<ToastType, number> = {
    success: 4000,
    info: 5000,
    warning: 7000,
    error: 0,
    loading: 0,
};

/**
 * The global toast service (§41, §117).
 *
 * Toasts report the outcome of an operation. They never replace inline form
 * errors and never carry validation messages — a message the user must act on
 * does not belong in something that disappears.
 */
export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([]);
    const timers = new Map<string, ReturnType<typeof setTimeout>>();

    function dismiss(id: string): void {
        const timer = timers.get(id);
        if (timer) {
            clearTimeout(timer);
            timers.delete(id);
        }
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    function push(
        type: ToastType,
        title: string,
        options: ToastOptions = {},
    ): string {
        // Collapse an identical toast already on screen rather than stacking
        // duplicates — a retried request should not produce five copies (§117).
        const duplicate = toasts.value.find(
            (toast) => toast.type === type && toast.title === title,
        );

        if (duplicate) {
            return duplicate.id;
        }

        const id = nextId();
        const duration = options.duration ?? DEFAULT_DURATION[type];

        toasts.value.push({
            id,
            type,
            title,
            description: options.description,
            duration,
            action: options.action,
            secondaryAction: options.secondaryAction,
        });

        if (duration > 0) {
            timers.set(
                id,
                setTimeout(() => dismiss(id), duration),
            );
        }

        return id;
    }

    function clear(): void {
        timers.forEach((timer) => clearTimeout(timer));
        timers.clear();
        toasts.value = [];
    }

    return {
        toasts,
        dismiss,
        clear,
        success: (title: string, o?: ToastOptions) => push('success', title, o),
        info: (title: string, o?: ToastOptions) => push('info', title, o),
        warning: (title: string, o?: ToastOptions) => push('warning', title, o),
        error: (title: string, o?: ToastOptions) => push('error', title, o),
        loading: (title: string, o?: ToastOptions) => push('loading', title, o),
    };
});
