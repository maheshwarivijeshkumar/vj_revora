<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import AppLayout from '@/layouts/AppLayout.vue';

type Template = { id: string; name: string; subject: string; body: string };

const props = defineProps<{
    sequence: {
        id: number;
        is_active: boolean;
        no_reply_after_hours: number;
        steps: {
            position: number;
            delay_hours: number;
            template_id: string | null;
        }[];
    };
    templates: Template[];
    placeholders: Record<string, string>;
    stats: Record<string, { label: string; tone: Tone; count: number }>;
}>();

const { can } = useAuthorization();

const schedule = useForm({
    is_active: props.sequence.is_active,
    no_reply_after_hours: String(props.sequence.no_reply_after_hours),
    steps: props.sequence.steps.map((s) => ({
        delay_hours: String(s.delay_hours),
        template_id: s.template_id ?? '',
    })),
});

const templateOptions = computed(() =>
    props.templates.map((t) => ({ value: t.id, label: t.name })),
);

function addStep(): void {
    const last = Number(schedule.steps.at(-1)?.delay_hours ?? 0);
    schedule.steps.push({ delay_hours: String(last + 24), template_id: '' });
}

function removeStep(index: number): void {
    schedule.steps.splice(index, 1);
}

function saveSchedule(): void {
    schedule.put('/settings/follow-up', { preserveScroll: true });
}

function describeDelay(hours: string): string {
    const h = Number(hours);

    if (h === 0) {
        return 'straight away';
    }

    return h % 24 === 0 && h >= 24
        ? `${h / 24} day${h === 24 ? '' : 's'} after the first`
        : `${h} hours after the first`;
}

// --- Templates ----------------------------------------------------------------

const editing = ref<string | 'new' | null>(null);
const template = useForm({ name: '', subject: '', body: '' });

function edit(t: Template | null): void {
    editing.value = t?.id ?? 'new';
    template.defaults({
        name: t?.name ?? '',
        subject: t?.subject ?? '',
        body: t?.body ?? '',
    });
    template.reset();
    template.clearErrors();
}

function saveTemplate(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };

    if (editing.value === 'new') {
        template.post('/settings/templates', options);
    } else {
        template.patch(`/settings/templates/${editing.value}`, options);
    }
}

function removeTemplate(t: Template): void {
    if (window.confirm(`Delete the template “${t.name}”?`)) {
        router.delete(`/settings/templates/${t.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Follow-up" />

    <AppLayout
        title="Follow-up"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Follow-up' }]"
    >
        <div class="space-y-4">
            <!-- Outcomes so far -->
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="(stat, key) in stats"
                    :key="key"
                    class="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2"
                >
                    <StatusBadge :tone="stat.tone" :label="stat.label" />
                    <span
                        class="text-[0.95rem] font-semibold text-strong tabular-nums"
                        >{{ stat.count }}</span
                    >
                </div>
            </div>

            <!-- Schedule -->
            <form
                class="rounded-xl border border-border bg-surface p-5"
                novalidate
                @submit.prevent="saveSchedule"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[0.98rem] font-semibold text-strong">
                            Automatic follow-up emails
                        </h2>
                        <p class="mt-0.5 max-w-2xl text-[0.85rem] text-muted">
                            Every new lead from your ads, website forms or API
                            gets these emails. A reply stops them and is scored
                            out of 10. Imported lists are never emailed
                            automatically.
                        </p>
                    </div>
                    <label
                        class="flex items-center gap-2 text-[0.88rem] font-medium text-strong"
                    >
                        <Checkbox
                            id="sequence-active"
                            v-model="schedule.is_active"
                            :disabled="!can('automation.manage')"
                        />
                        Turned on
                    </label>
                </div>

                <ol class="mt-4 space-y-2">
                    <li
                        v-for="(step, index) in schedule.steps"
                        :key="index"
                        class="rounded-lg border border-border p-3"
                    >
                        <div
                            class="grid gap-3 sm:grid-cols-[1.5rem_11rem_minmax(0,1fr)_2.75rem] sm:items-start"
                        >
                            <span
                                class="text-[0.9rem] font-semibold text-muted sm:pt-8"
                                >{{ index + 1 }}.</span
                            >
                            <FormField
                                :id="`step-${index}-delay`"
                                label="Hours after first"
                                :hint="describeDelay(step.delay_hours)"
                            >
                                <TextInput
                                    :id="`step-${index}-delay`"
                                    v-model="step.delay_hours"
                                    type="number"
                                    min="0"
                                />
                            </FormField>
                            <FormField
                                :id="`step-${index}-template`"
                                label="Email"
                                :error="
                                    schedule.errors[
                                        `steps.${index}.template_id`
                                    ]
                                "
                            >
                                <SelectMenu
                                    :id="`step-${index}-template`"
                                    v-model="step.template_id"
                                    :options="templateOptions"
                                    placeholder="Choose a template"
                                />
                            </FormField>
                            <div>
                                <span class="invisible block text-[0.8125rem] font-medium" aria-hidden="true">Remove</span>
                                <div class="mt-1.5">
                                <Button
                                    v-if="schedule.steps.length > 1"
                                    variant="danger"
                                    size="icon"
                                    class="size-11"
                                    :aria-label="`Remove email ${index + 1}`"
                                    @click="removeStep(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ol>

                <p
                    v-if="schedule.errors.steps"
                    role="alert"
                    class="mt-2 text-[0.85rem] text-danger"
                >
                    {{ schedule.errors.steps }}
                </p>

                <div class="mt-3">
                    <Button
                        v-if="schedule.steps.length < 10"
                        variant="secondary"
                        size="sm"
                        @click="addStep"
                    >
                        <Plus class="size-4" />
                        Add an email
                    </Button>
                </div>

                <div class="mt-4 max-w-xs">
                    <FormField
                        id="no-reply"
                        label="No reply after (hours)"
                        hint="Then the sequence stops and the lead is scored 0/10."
                        :error="schedule.errors.no_reply_after_hours"
                    >
                        <TextInput
                            id="no-reply"
                            v-model="schedule.no_reply_after_hours"
                            type="number"
                            min="1"
                        />
                    </FormField>
                </div>

                <div v-if="can('automation.manage')" class="mt-5">
                    <Button
                        type="submit"
                        variant="brand"
                        size="lg"
                        :loading="schedule.processing"
                        >Save schedule</Button
                    >
                </div>
            </form>

            <!-- Templates -->
            <section class="rounded-xl border border-border bg-surface">
                <div
                    class="flex items-center justify-between border-b border-border px-5 py-3"
                >
                    <h2 class="text-[0.95rem] font-semibold text-strong">
                        Email templates
                    </h2>
                    <Button
                        v-if="can('template.create')"
                        variant="secondary"
                        size="sm"
                        @click="edit(null)"
                    >
                        <Plus class="size-4" />
                        New template
                    </Button>
                </div>

                <form
                    v-if="editing"
                    class="border-b border-border p-5"
                    novalidate
                    @submit.prevent="saveTemplate"
                >
                    <div class="grid gap-4">
                        <FormField
                            id="tpl-name"
                            label="Name"
                            required
                            hint="Only you see this."
                            :error="template.errors.name"
                        >
                            <TextInput
                                id="tpl-name"
                                v-model="template.name"
                                :invalid="Boolean(template.errors.name)"
                            />
                        </FormField>
                        <FormField
                            id="tpl-subject"
                            label="Subject"
                            required
                            :error="template.errors.subject"
                        >
                            <TextInput
                                id="tpl-subject"
                                v-model="template.subject"
                                :invalid="Boolean(template.errors.subject)"
                            />
                        </FormField>
                        <FormField
                            id="tpl-body"
                            label="Message"
                            required
                            hint="An unsubscribe link is added at the bottom automatically."
                            :error="template.errors.body"
                        >
                            <TextArea
                                id="tpl-body"
                                v-model="template.body"
                                :rows="9"
                                :invalid="Boolean(template.errors.body)"
                            />
                        </FormField>
                    </div>
                    <p class="mt-3 text-[0.8rem] text-muted">
                        Placeholders:
                        <span
                            v-for="(label, key) in placeholders"
                            :key="key"
                            class="mr-2 inline-block"
                            :title="label"
                        >
                            <code
                                class="rounded bg-surface-alt px-1 font-mono text-strong"
                                v-text="`{{${key}}}`"
                            />
                        </span>
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Button
                            type="submit"
                            variant="brand"
                            size="lg"
                            :loading="template.processing"
                            >Save template</Button
                        >
                        <Button
                            variant="ghost"
                            size="lg"
                            @click="editing = null"
                            >Cancel</Button
                        >
                    </div>
                </form>

                <ul class="divide-y divide-border">
                    <li
                        v-for="t in templates"
                        :key="t.id"
                        class="flex items-start justify-between gap-3 px-5 py-3"
                    >
                        <div class="min-w-0">
                            <p class="text-[0.92rem] font-medium text-strong">
                                {{ t.name }}
                            </p>
                            <p class="truncate text-[0.82rem] text-muted">
                                {{ t.subject }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button
                                v-if="can('template.update')"
                                variant="ghost"
                                size="sm"
                                :aria-label="`Edit ${t.name}`"
                                @click="edit(t)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <button
                                v-if="can('template.delete')"
                                type="button"
                                class="rounded-md p-1.5 text-danger transition-colors hover:bg-danger-soft"
                                :aria-label="`Delete ${t.name}`"
                                @click="removeTemplate(t)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
