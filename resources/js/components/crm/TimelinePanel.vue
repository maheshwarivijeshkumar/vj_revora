<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CircleDot,
    Mail,
    MessageSquare,
    NotebookPen,
    Phone,
    Pin,
    PinOff,
    Plus,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';

export type TimelineActivity = {
    id: number;
    type: string;
    type_label: string;
    summary: string;
    body: string | null;
    user: string | null;
    duration_minutes: number | null;
    occurred_at: string;
};

export type TimelineNote = {
    id: number;
    body: string;
    is_pinned: boolean;
    user: string | null;
    created_at: string | null;
};

export type TimelineTask = {
    id: number;
    uuid: string;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    status_tone: string;
    priority: string;
    priority_label: string;
    due_at: string | null;
    is_overdue: boolean;
    assignee: string | null;
};

const { timeZone } = useClock();
const props = defineProps<{
    /** `lead`, `contact`, `company` or `deal` — the controller's allowlist. */
    subjectType: string;
    subjectId: number;
    activities: TimelineActivity[];
    notes: TimelineNote[];
    tasks: TimelineTask[];
    options?: {
        activityTypes: {
            value: string;
            label: string;
            has_duration: boolean;
        }[];
        taskStatuses: { value: string; label: string; tone: string }[];
        taskPriorities: { value: string; label: string; tone: string }[];
        owners: SelectOption[];
    };
}>();

const { can } = useAuthorization();

type Composer = 'note' | 'task' | 'activity' | null;

const composing = ref<Composer>(null);

const ICONS: Record<string, typeof Phone> = {
    call: Phone,
    meeting: Users,
    email: Mail,
    message: MessageSquare,
    note: NotebookPen,
    task: CircleDot,
};

const subject = computed(() => ({
    subject_type: props.subjectType,
    subject_id: props.subjectId,
}));

const noteForm = useForm({ body: '', is_pinned: false });
const taskForm = useForm({
    title: '',
    description: '',
    priority: 'normal',
    due_at: '',
    assigned_to: '',
});
const activityForm = useForm({
    type: 'call',
    summary: '',
    body: '',
    occurred_at: '',
    duration_minutes: '',
});

/** Whether the chosen activity type is one a duration makes sense for. */
const needsDuration = computed(
    () =>
        props.options?.activityTypes.find((t) => t.value === activityForm.type)
            ?.has_duration ?? false,
);

function close(): void {
    composing.value = null;
}

function submitNote(): void {
    if (!noteForm.body.trim()) {
        return;
    }

    noteForm
        .transform((data) => ({ ...data, ...subject.value }))
        .post('/notes', {
            preserveScroll: true,
            onSuccess: () => {
                noteForm.reset();
                close();
            },
        });
}

function submitTask(): void {
    if (!taskForm.title.trim()) {
        return;
    }

    taskForm
        .transform((data) => ({ ...data, ...subject.value }))
        .post('/tasks', {
            preserveScroll: true,
            onSuccess: () => {
                taskForm.reset();
                close();
            },
        });
}

function submitActivity(): void {
    if (!activityForm.summary.trim()) {
        return;
    }

    activityForm
        .transform((data) => ({ ...data, ...subject.value }))
        .post('/activities', {
            preserveScroll: true,
            onSuccess: () => {
                activityForm.reset();
                close();
            },
        });
}

function setTaskStatus(task: TimelineTask, status: string): void {
    router.patch(`/tasks/${task.uuid}`, { status }, { preserveScroll: true });
}

function togglePin(note: TimelineNote): void {
    router.patch(
        `/notes/${note.id}`,
        { is_pinned: !note.is_pinned },
        { preserveScroll: true },
    );
}

function removeNote(note: TimelineNote): void {
    if (!window.confirm('Remove this note? It can be restored later.')) {
        return;
    }

    router.delete(`/notes/${note.id}`, { preserveScroll: true });
}

function removeTask(task: TimelineTask): void {
    if (!window.confirm(`Remove “${task.title}”?`)) {
        return;
    }

    router.delete(`/tasks/${task.uuid}`, { preserveScroll: true });
}

function formatDate(value: string | null, withTime = false): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, { timeZone: timeZone.value,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}
</script>

<template>
    <div class="space-y-4">
        <!-- What can be added. Each opens one composer at a time: three forms
             stacked open is a wall, and nobody fills in three at once. -->
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can('note.create')"
                :variant="composing === 'note' ? 'brand' : 'secondary'"
                size="sm"
                @click="composing = composing === 'note' ? null : 'note'"
            >
                <NotebookPen class="size-4" />
                Add note
            </Button>
            <Button
                v-if="can('task.create')"
                :variant="composing === 'task' ? 'brand' : 'secondary'"
                size="sm"
                @click="composing = composing === 'task' ? null : 'task'"
            >
                <CircleDot class="size-4" />
                Add task
            </Button>
            <Button
                v-if="can('activity.create')"
                :variant="composing === 'activity' ? 'brand' : 'secondary'"
                size="sm"
                @click="
                    composing = composing === 'activity' ? null : 'activity'
                "
            >
                <Plus class="size-4" />
                Log a call or meeting
            </Button>
        </div>

        <!-- Note composer -->
        <form
            v-if="composing === 'note'"
            class="rounded-xl border border-border bg-surface p-4"
            novalidate
            @submit.prevent="submitNote"
        >
            <FormField
                id="note-body"
                label="Note"
                required
                :error="noteForm.errors.body"
            >
                <TextArea
                    id="note-body"
                    v-model="noteForm.body"
                    :rows="4"
                    placeholder="What happened, and what it means for this deal."
                    :invalid="Boolean(noteForm.errors.body)"
                />
            </FormField>

            <label
                class="mt-3 flex cursor-pointer items-center gap-2 text-[0.85rem]"
            >
                <input
                    v-model="noteForm.is_pinned"
                    type="checkbox"
                    class="size-4 rounded border-border"
                />
                <span class="text-body">
                    Pin this to the top — the first thing somebody should read
                </span>
            </label>

            <div class="mt-3 flex gap-2">
                <Button
                    type="submit"
                    variant="brand"
                    size="sm"
                    :loading="noteForm.processing"
                >
                    Save note
                </Button>
                <Button variant="ghost" size="sm" @click="close">Cancel</Button>
            </div>
        </form>

        <!-- Task composer -->
        <form
            v-if="composing === 'task'"
            class="rounded-xl border border-border bg-surface p-4"
            novalidate
            @submit.prevent="submitTask"
        >
            <FormField
                id="task-title"
                label="Task"
                required
                :error="taskForm.errors.title"
            >
                <TextInput
                    id="task-title"
                    v-model="taskForm.title"
                    placeholder="Send the proposal"
                    :invalid="Boolean(taskForm.errors.title)"
                />
            </FormField>

            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <FormField
                    id="task-due"
                    label="Due"
                    optional-label
                    :error="taskForm.errors.due_at"
                >
                    <TextInput
                        id="task-due"
                        v-model="taskForm.due_at"
                        type="date"
                        :invalid="Boolean(taskForm.errors.due_at)"
                    />
                </FormField>

                <FormField
                    id="task-priority"
                    label="Priority"
                    :error="taskForm.errors.priority"
                >
                    <SelectMenu
                        id="task-priority"
                        v-model="taskForm.priority"
                        :options="
                            (options?.taskPriorities ?? []).map((p) => ({
                                value: p.value,
                                label: p.label,
                            }))
                        "
                    />
                </FormField>

                <FormField
                    id="task-assignee"
                    label="Assign to"
                    optional-label
                    hint="Defaults to you."
                    :error="taskForm.errors.assigned_to"
                >
                    <SelectMenu
                        id="task-assignee"
                        v-model="taskForm.assigned_to"
                        :options="[
                            { value: '', label: 'Me' },
                            ...(options?.owners ?? []),
                        ]"
                    />
                </FormField>
            </div>

            <div class="mt-3 flex gap-2">
                <Button
                    type="submit"
                    variant="brand"
                    size="sm"
                    :loading="taskForm.processing"
                >
                    Create task
                </Button>
                <Button variant="ghost" size="sm" @click="close">Cancel</Button>
            </div>
        </form>

        <!-- Activity composer -->
        <form
            v-if="composing === 'activity'"
            class="rounded-xl border border-border bg-surface p-4"
            novalidate
            @submit.prevent="submitActivity"
        >
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField
                    id="activity-type"
                    label="What was it"
                    required
                    :error="activityForm.errors.type"
                >
                    <SelectMenu
                        id="activity-type"
                        v-model="activityForm.type"
                        :options="
                            (options?.activityTypes ?? []).map((t) => ({
                                value: t.value,
                                label: t.label,
                            }))
                        "
                    />
                </FormField>

                <FormField
                    id="activity-when"
                    label="When"
                    optional-label
                    hint="Leave blank for now."
                    :error="activityForm.errors.occurred_at"
                >
                    <TextInput
                        id="activity-when"
                        v-model="activityForm.occurred_at"
                        type="date"
                        hint
                        :invalid="Boolean(activityForm.errors.occurred_at)"
                    />
                </FormField>
            </div>

            <FormField
                id="activity-summary"
                label="Summary"
                required
                class="mt-3"
                :error="activityForm.errors.summary"
            >
                <TextInput
                    id="activity-summary"
                    v-model="activityForm.summary"
                    placeholder="Discovery call with procurement"
                    :invalid="Boolean(activityForm.errors.summary)"
                />
            </FormField>

            <FormField
                v-if="needsDuration"
                id="activity-duration"
                label="How long, in minutes"
                optional-label
                class="mt-3"
                :error="activityForm.errors.duration_minutes"
            >
                <TextInput
                    id="activity-duration"
                    v-model="activityForm.duration_minutes"
                    type="number"
                    placeholder="25"
                    :invalid="Boolean(activityForm.errors.duration_minutes)"
                />
            </FormField>

            <FormField
                id="activity-body"
                label="Notes"
                optional-label
                class="mt-3"
                :error="activityForm.errors.body"
            >
                <TextArea
                    id="activity-body"
                    v-model="activityForm.body"
                    :rows="3"
                    :invalid="Boolean(activityForm.errors.body)"
                />
            </FormField>

            <div class="mt-3 flex gap-2">
                <Button
                    type="submit"
                    variant="brand"
                    size="sm"
                    :loading="activityForm.processing"
                >
                    Log it
                </Button>
                <Button variant="ghost" size="sm" @click="close">Cancel</Button>
            </div>
        </form>

        <!-- Outstanding tasks, above the history: what still needs doing beats
             what already happened. -->
        <div
            v-if="tasks.length"
            class="overflow-hidden rounded-xl border border-border bg-surface"
        >
            <h3
                class="border-b border-border px-4 py-2.5 text-[0.88rem] font-semibold text-strong"
            >
                Tasks
            </h3>
            <ul class="divide-y divide-border-soft">
                <li
                    v-for="task in tasks"
                    :key="task.uuid"
                    class="flex flex-wrap items-center gap-3 px-4 py-2.5"
                >
                    <button
                        v-if="can('task.update')"
                        type="button"
                        class="flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                        :class="
                            task.status === 'done'
                                ? 'border-success bg-success text-white'
                                : 'border-border hover:border-success'
                        "
                        :aria-label="
                            task.status === 'done'
                                ? `Reopen ${task.title}`
                                : `Mark ${task.title} done`
                        "
                        @click="
                            setTaskStatus(
                                task,
                                task.status === 'done' ? 'open' : 'done',
                            )
                        "
                    >
                        <Check v-if="task.status === 'done'" class="size-3" />
                    </button>

                    <span class="min-w-0 flex-1">
                        <span
                            class="block text-[0.9rem]"
                            :class="
                                task.status === 'done'
                                    ? 'text-muted line-through'
                                    : 'text-strong'
                            "
                        >
                            {{ task.title }}
                        </span>
                        <span class="block text-[0.8rem] text-muted">
                            <template v-if="task.due_at">
                                <span
                                    :class="
                                        task.is_overdue ? 'text-danger' : ''
                                    "
                                >
                                    {{ task.is_overdue ? 'Overdue —' : 'Due' }}
                                    {{ formatDate(task.due_at) }}
                                </span>
                            </template>
                            <template v-else>No due date</template>
                            <template v-if="task.assignee">
                                · {{ task.assignee }}
                            </template>
                        </span>
                    </span>

                    <StatusBadge
                        :tone="task.status_tone as never"
                        :label="task.status_label"
                    />

                    <button
                        v-if="can('task.delete')"
                        type="button"
                        class="rounded-md p-1.5 text-muted transition-colors hover:bg-danger-soft hover:text-danger"
                        :aria-label="`Remove ${task.title}`"
                        @click="removeTask(task)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </li>
            </ul>
        </div>

        <!-- Notes, pinned first -->
        <div
            v-if="notes.length"
            class="overflow-hidden rounded-xl border border-border bg-surface"
        >
            <h3
                class="border-b border-border px-4 py-2.5 text-[0.88rem] font-semibold text-strong"
            >
                Notes
            </h3>
            <ul class="divide-y divide-border-soft">
                <li
                    v-for="note in notes"
                    :key="note.id"
                    class="px-4 py-3"
                    :class="note.is_pinned ? 'bg-surface-alt' : ''"
                >
                    <div class="flex items-start gap-2">
                        <p
                            class="min-w-0 flex-1 text-[0.9rem] whitespace-pre-line text-body"
                        >
                            {{ note.body }}
                        </p>
                        <button
                            v-if="can('note.update')"
                            type="button"
                            class="shrink-0 rounded-md p-1.5 text-muted transition-colors hover:bg-surface-sunken hover:text-strong"
                            :aria-label="
                                note.is_pinned ? 'Unpin note' : 'Pin note'
                            "
                            @click="togglePin(note)"
                        >
                            <component
                                :is="note.is_pinned ? PinOff : Pin"
                                class="size-3.5"
                            />
                        </button>
                        <button
                            v-if="can('note.delete')"
                            type="button"
                            class="shrink-0 rounded-md p-1.5 text-muted transition-colors hover:bg-danger-soft hover:text-danger"
                            aria-label="Remove note"
                            @click="removeNote(note)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                    <p class="mt-1 text-[0.78rem] text-soft">
                        <span v-if="note.is_pinned" class="text-primary-600">
                            Pinned ·
                        </span>
                        {{ note.user ?? 'System' }} ·
                        {{ formatDate(note.created_at, true) }}
                    </p>
                </li>
            </ul>
        </div>

        <!-- The history -->
        <div
            v-if="
                activities.length === 0 &&
                notes.length === 0 &&
                tasks.length === 0
            "
            class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
        >
            Nothing recorded yet. Add a note, create a task, or log a call.
        </div>

        <ol v-else-if="activities.length" class="space-y-0">
            <li
                v-for="(activity, index) in activities"
                :key="activity.id"
                class="relative flex gap-3 pb-4 pl-1"
            >
                <span
                    v-if="index < activities.length - 1"
                    class="absolute top-6 left-[0.85rem] h-full w-px bg-border"
                    aria-hidden="true"
                />
                <span
                    class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-alt ring-3 ring-[var(--color-page)]"
                    aria-hidden="true"
                >
                    <component
                        :is="ICONS[activity.type] ?? CircleDot"
                        class="size-3.5 text-muted"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[0.9rem] font-medium text-strong">
                        {{ activity.summary }}
                    </p>
                    <p
                        v-if="activity.body"
                        class="mt-0.5 text-[0.85rem] whitespace-pre-line text-body"
                    >
                        {{ activity.body }}
                    </p>
                    <p class="mt-0.5 text-[0.8rem] text-muted tabular-nums">
                        {{ activity.type_label }} ·
                        {{ formatDate(activity.occurred_at, true) }}
                        <template v-if="activity.duration_minutes">
                            · {{ activity.duration_minutes }} min
                        </template>
                        <template v-if="activity.user">
                            · {{ activity.user }}
                        </template>
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
