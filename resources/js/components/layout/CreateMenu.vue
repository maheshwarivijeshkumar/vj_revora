<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    ChevronDown,
    Handshake,
    Plus,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useAuthorization } from '@/composables/useAuthorization';

/**
 * The global create button (§70).
 *
 * Each entry lands on that entity's list with `?new=1`, which the list reads
 * and opens its own create drawer for. One form per entity, used from wherever
 * it is reached — rather than a second copy of each form living up here.
 */
const { can } = useAuthorization();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const items = computed(() =>
    [
        {
            label: 'Lead',
            href: '/leads?new=1',
            icon: UserPlus,
            permission: 'lead.create',
        },
        {
            label: 'Contact',
            href: '/contacts?new=1',
            icon: Users,
            permission: 'contact.create',
        },
        {
            label: 'Company',
            href: '/companies?new=1',
            icon: Building2,
            permission: 'company.create',
        },
        {
            label: 'Deal',
            href: '/deals?new=1',
            icon: Handshake,
            permission: 'deal.create',
        },
    ].filter((item) => can(item.permission)),
);

function go(href: string): void {
    open.value = false;
    router.visit(href);
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
    <!-- Nothing to offer someone who may not create anything, so the button is
         absent rather than present and disabled. -->
    <div v-if="items.length" ref="root" class="relative">
        <Button
            variant="primary"
            size="sm"
            class="hidden sm:inline-flex"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Plus class="size-4" />
            Create
            <ChevronDown class="size-3.5 opacity-70" aria-hidden="true" />
        </Button>

        <Transition
            enter-active-class="transition duration-150 motion-reduce:transition-none"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 motion-reduce:transition-none"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <ul
                v-if="open"
                role="menu"
                class="absolute right-0 z-40 mt-1.5 w-44 overflow-hidden rounded-lg border border-border bg-surface py-1 shadow-card"
            >
                <li v-for="item in items" :key="item.href" role="none">
                    <button
                        type="button"
                        role="menuitem"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[0.9rem] text-body transition-colors hover:bg-surface-alt hover:text-strong"
                        @click="go(item.href)"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 shrink-0 text-soft"
                            aria-hidden="true"
                        />
                        {{ item.label }}
                    </button>
                </li>
            </ul>
        </Transition>
    </div>
</template>
