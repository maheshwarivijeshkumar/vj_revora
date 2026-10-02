<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SiteFooter from '@/components/marketing/SiteFooter.vue';
import SiteNav from '@/components/marketing/SiteNav.vue';
import ToastHost from '@/components/overlay/ToastHost.vue';
import { useThemeStore } from '@/stores/theme';
import { useToastStore } from '@/stores/toast';

defineProps<{
    brand: { name: string; tagline: string; assets: Record<string, string> };
    contact: Record<string, string>;
    social: Record<string, string>;
}>();

const page = usePage();
const theme = useThemeStore();
const toasts = useToastStore();

onMounted(() => theme.init());

watch(
    () => page.props.flash,
    (flash) => {
        const f = (flash ?? {}) as Record<string, string | null>;
        if (f.success) toasts.success(f.success);
        if (f.error) toasts.error(f.error);
        if (f.info) toasts.info(f.info);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="min-h-dvh bg-surface">
        <SiteNav :brand="brand" />
        <main>
            <slot />
        </main>
        <SiteFooter :brand="brand" :contact="contact" :social="social" />
        <ToastHost />
    </div>
</template>
