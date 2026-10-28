<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useClock } from '@/composables/useClock';

type Notice = {
    id: number;
    title: string;
    body: string;
    href: string | null;
    read_at: string | null;
    created_at: string | null;
};

const props = defineProps<{ requestOpen?: boolean }>();
const emit = defineEmits<{ 'update:requestOpen': [open: boolean] }>();

const page = usePage();
const { formatDateTime } = useClock();

const root = ref<HTMLElement | null>(null);
const open = ref(false);

const payload = computed(
    () =>
        (page.props.notifications as { unread: number; items: Notice[] } | undefined) ?? {
            unread: 0,
            items: [],
        },
);

watch(
    () => props.requestOpen,
    (value) => {
        if (value) {
            open.value = true;
            emit('update:requestOpen', false);
        }
    },
);

function toggle(): void {
    open.value = !open.value;
}

function read(notice: Notice): void {
    const visit = (): void => {
        open.value = false;

        if (notice.href) {
            router.visit(notice.href);
        }
    };

    if (notice.read_at) {
        visit();

        return;
    }

    router.patch(`/account/notifications/${notice.id}`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: visit,
    });
}

function onPointerDown(event: PointerEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <Button
            variant="ghost"
            size="icon"
            class="relative"
            aria-label="Notifications"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="toggle"
        >
            <Bell class="size-4.5" />
            <span
                v-if="payload.unread > 0"
                class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[0.65rem] font-semibold text-white"
            >
                {{ payload.unread > 9 ? '9+' : payload.unread }}
            </span>
        </Button>

        <div
            v-if="open"
            role="menu"
            class="absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-xl border border-border bg-surface shadow-card"
        >
            <div class="flex items-center justify-between border-b border-border px-3 py-2.5">
                <p class="text-[0.92rem] font-semibold text-strong">Notifications</p>
                <p class="text-[0.75rem] text-muted">
                    {{ payload.unread }} unread
                </p>
            </div>

            <p v-if="payload.items.length === 0" class="px-3 py-6 text-center text-[0.85rem] text-muted">
                Nothing new. A lead status change will show up here.
            </p>

            <ul v-else class="max-h-96 overflow-y-auto py-1">
                <li v-for="notice in payload.items" :key="notice.id">
                    <button
                        type="button"
                        role="menuitem"
                        class="flex w-full items-start gap-2 px-3 py-2.5 text-left hover:bg-surface-alt"
                        @click="read(notice)"
                    >
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="notice.read_at ? 'bg-transparent' : 'bg-primary-600'"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <span
                                class="block text-[0.88rem] text-strong"
                                :class="notice.read_at ? 'font-medium' : 'font-semibold'"
                            >
                                {{ notice.title }}
                            </span>
                            <span class="mt-0.5 block text-[0.8rem] text-muted">{{ notice.body }}</span>
                            <span v-if="notice.created_at" class="mt-1 block text-[0.75rem] text-soft tabular-nums">
                                {{ formatDateTime(notice.created_at) }}
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
