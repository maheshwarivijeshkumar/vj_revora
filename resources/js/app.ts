import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME || 'Revora';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp: (app) => {
        // Pinia holds cross-page chrome state only — theme, sidebar, toasts,
        // saved filters. Server state stays with Inertia (ADR-001).
        app.use(createPinia());

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#059669',
    },
});
