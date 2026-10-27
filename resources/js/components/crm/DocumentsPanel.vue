<script setup lang="ts">
/**
 * Files attached to a record (§21, §44).
 *
 * One component for every entity, like the timeline: the subject arrives as a
 * type and an id and the server resolves it through its own allowlist.
 */
import { router, useForm } from '@inertiajs/vue3';
import {
    Download,
    ExternalLink,
    FileImage,
    FileSpreadsheet,
    FileText,
    Paperclip,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormField from '@/components/ui/FormField.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';

export type AttachedDocument = {
    uuid: string;
    name: string;
    original_name: string;
    description: string | null;
    extension: string;
    mime_type: string;
    size: string;
    can_preview: boolean;
    uploaded_by: string | null;
    created_at: string | null;
};

export type DocumentConstraints = {
    extensions: string[];
    max_size_kb: number;
    accept: string;
};

const props = defineProps<{
    /** `lead`, `contact`, `company` or `deal` — the server's allowlist. */
    subjectType: string;
    subjectId: number;
    documents: AttachedDocument[];
    constraints?: DocumentConstraints;
}>();

const { can } = useAuthorization();

const composing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
/**
 * Checked here as well as on the server, so a 20 MB upload is refused before
 * it is sent rather than after (§42, §59). The server still decides.
 */
const localError = ref<string | null>(null);

const form = useForm<{
    file: File | null;
    title: string;
    description: string;
}>({ file: null, title: '', description: '' });

const maxSizeLabel = computed(() => {
    const kb = props.constraints?.max_size_kb ?? 0;

    return kb >= 1024 ? `${Math.round(kb / 1024)} MB` : `${kb} KB`;
});

/** A per-family icon, so the kind of file reads at a glance (§127). */
function iconFor(document: AttachedDocument): typeof FileText {
    if (document.mime_type.startsWith('image/')) {
        return FileImage;
    }

    if (['csv', 'xls', 'xlsx'].includes(document.extension)) {
        return FileSpreadsheet;
    }

    return FileText;
}

function onFileChosen(event: Event): void {
    localError.value = null;

    const chosen = (event.target as HTMLInputElement).files?.[0] ?? null;

    form.file = chosen;

    if (chosen === null || props.constraints === undefined) {
        return;
    }

    const extension = chosen.name.split('.').pop()?.toLowerCase() ?? '';

    if (!props.constraints.extensions.includes(extension)) {
        localError.value = `That file type cannot be attached. Allowed: ${props.constraints.extensions.join(', ')}.`;

        return;
    }

    if (chosen.size > props.constraints.max_size_kb * 1024) {
        localError.value = `That file is larger than ${maxSizeLabel.value}.`;
    }
}

function reset(): void {
    form.reset();
    form.clearErrors();
    localError.value = null;
    composing.value = false;

    if (fileInput.value !== null) {
        fileInput.value.value = '';
    }
}

function submit(): void {
    if (form.file === null) {
        localError.value = 'Choose a file to attach.';

        return;
    }

    if (localError.value !== null) {
        return;
    }

    form.transform((data) => ({
        ...data,
        subject_type: props.subjectType,
        subject_id: props.subjectId,
    })).post('/documents', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: reset,
    });
}

function remove(document: AttachedDocument): void {
    const confirmed = window.confirm(
        `Remove ${document.name}? It can be restored by an administrator.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(`/documents/${document.uuid}`, { preserveScroll: true });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="can('document.create')" class="flex flex-wrap gap-2">
            <Button
                :variant="composing ? 'brand' : 'secondary'"
                size="sm"
                @click="composing ? reset() : (composing = true)"
            >
                <Upload class="size-4" />
                Attach a file
            </Button>
        </div>

        <!-- Upload form -->
        <form
            v-if="composing"
            class="rounded-xl border border-border bg-surface p-4"
            novalidate
            @submit.prevent="submit"
        >
            <FormField
                id="document-file"
                label="File"
                required
                :hint="
                    constraints
                        ? `Up to ${maxSizeLabel}. ${constraints.extensions.join(', ')}.`
                        : undefined
                "
                :error="localError ?? form.errors.file"
            >
                <input
                    id="document-file"
                    ref="fileInput"
                    type="file"
                    :accept="constraints?.accept"
                    class="block w-full cursor-pointer rounded-lg border border-border bg-surface-alt text-[0.88rem] text-body file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-surface file:px-3 file:py-2 file:text-body"
                    :aria-invalid="Boolean(localError ?? form.errors.file)"
                    @change="onFileChosen"
                />
            </FormField>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <FormField
                    id="document-title"
                    label="Name"
                    optional-label
                    hint="Defaults to the filename."
                    :error="form.errors.title"
                >
                    <TextInput
                        id="document-title"
                        v-model="form.title"
                        placeholder="Signed contract"
                        :invalid="Boolean(form.errors.title)"
                    />
                </FormField>

                <FormField
                    id="document-description"
                    label="Note"
                    optional-label
                    :error="form.errors.description"
                >
                    <TextArea
                        id="document-description"
                        v-model="form.description"
                        :rows="2"
                        placeholder="What this is, and why it matters."
                        :invalid="Boolean(form.errors.description)"
                    />
                </FormField>
            </div>

            <!-- Real progress, because a large upload with no feedback reads
                 as a broken button (§117). -->
            <div
                v-if="form.progress"
                class="mt-3"
                role="status"
                aria-live="polite"
            >
                <div class="h-1.5 overflow-hidden rounded-full bg-surface-alt">
                    <div
                        class="h-full rounded-full bg-brand transition-[width]"
                        :style="{ width: `${form.progress.percentage ?? 0}%` }"
                    />
                </div>
                <p class="mt-1 text-[0.8rem] text-muted">
                    Uploading… {{ form.progress.percentage ?? 0 }}%
                </p>
            </div>

            <div class="mt-3 flex gap-2">
                <Button
                    type="submit"
                    variant="brand"
                    size="sm"
                    :loading="form.processing"
                >
                    Attach
                </Button>
                <Button variant="ghost" size="sm" @click="reset">Cancel</Button>
            </div>
        </form>

        <!-- The files themselves -->
        <ul v-if="documents.length > 0" class="space-y-2">
            <li
                v-for="document in documents"
                :key="document.uuid"
                class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface p-3"
            >
                <component
                    :is="iconFor(document)"
                    class="size-5 shrink-0 text-muted"
                    aria-hidden="true"
                />

                <div class="min-w-0 flex-1">
                    <p class="truncate text-[0.9rem] font-medium text-strong">
                        {{ document.name }}
                    </p>
                    <p class="text-[0.8rem] text-muted">
                        {{ document.size }} ·
                        {{ document.uploaded_by ?? 'Unknown' }} ·
                        {{ formatDate(document.created_at) }}
                    </p>
                    <p
                        v-if="document.description"
                        class="mt-1 text-[0.82rem] text-body"
                    >
                        {{ document.description }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <!-- Plain links, not Inertia visits: these are file
                         responses, not pages. -->
                    <a
                        v-if="document.can_preview"
                        :href="`/documents/${document.uuid}?inline=1`"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[0.82rem] text-body hover:bg-surface-alt"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" />
                        Open
                    </a>
                    <a
                        :href="`/documents/${document.uuid}`"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[0.82rem] text-body hover:bg-surface-alt"
                    >
                        <Download class="size-4" aria-hidden="true" />
                        Download
                    </a>
                    <button
                        v-if="can('document.delete')"
                        type="button"
                        class="rounded-lg p-1.5 text-muted hover:bg-surface-alt hover:text-danger"
                        :aria-label="`Remove ${document.name}`"
                        @click="remove(document)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </li>
        </ul>

        <EmptyState
            v-else
            title="No files yet"
            description="Contracts, proposals and anything else worth keeping with this record live here."
        >
            <template #icon>
                <Paperclip class="size-5" />
            </template>
        </EmptyState>
    </div>
</template>
