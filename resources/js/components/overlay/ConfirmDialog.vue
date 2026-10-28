<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        message: string;
        confirmLabel?: string;
        busy?: boolean;
    }>(),
    { confirmLabel: 'Delete', busy: false },
);

const emit = defineEmits<{ cancel: []; confirm: [] }>();

const dialog = ref<HTMLElement | null>(null);

function cancel(): void {
    if (props.busy) {
        return;
    }

    emit('cancel');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        cancel();
    }
}

watch(
    () => props.open,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            await nextTick();
            dialog.value?.querySelector<HTMLButtonElement>('button')?.focus();
        }
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            @keydown="onKeydown"
        >
            <div class="absolute inset-0 bg-[var(--overlay)]" @click="cancel" />

            <div
                ref="dialog"
                class="relative w-full max-w-md rounded-xl border border-border bg-surface p-5 shadow-lg"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirm-dialog-title"
                aria-describedby="confirm-dialog-message"
            >
                <h2
                    id="confirm-dialog-title"
                    class="text-[1.05rem] font-semibold text-strong"
                >
                    {{ title }}
                </h2>
                <p
                    id="confirm-dialog-message"
                    class="mt-2 text-[0.9rem] text-body"
                >
                    {{ message }}
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="busy"
                        @click="cancel"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="danger"
                        :loading="busy"
                        @click="emit('confirm')"
                    >
                        {{ confirmLabel }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
