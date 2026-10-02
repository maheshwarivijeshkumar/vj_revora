<script setup lang="ts">
/**
 * Inner-page hero. Left-aligned and constrained to a readable measure, so
 * sub-pages do not all open with the same centred block the home page uses.
 */
defineProps<{ kicker: string; title: string; lede: string }>();
</script>

<template>
    <section class="border-b border-border">
        <div class="mx-auto max-w-[78rem] px-5 py-16 sm:py-20 lg:px-8">
            <p
                class="text-[0.72rem] font-semibold tracking-[0.13em] text-muted uppercase"
            >
                {{ kicker }}
            </p>
            <h1
                class="mt-4 max-w-[18ch] font-display text-[2.4rem] leading-[1.06] font-bold tracking-[-0.03em] text-strong sm:text-[3.4rem]"
            >
                {{ title }}
            </h1>
            <p
                class="mt-5 max-w-[52ch] text-[1.05rem] leading-relaxed text-muted"
            >
                {{ lede }}
            </p>
            <div v-if="$slots.actions" class="mt-8 flex flex-wrap gap-3">
                <slot name="actions" />
            </div>
        </div>
    </section>
</template>
