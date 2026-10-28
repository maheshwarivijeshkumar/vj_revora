<script setup lang="ts">
import { useClock } from '@/composables/useClock';
import { Link, router } from '@inertiajs/vue3';
import { Mail, MessageCircle, Send, Square } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import StatusBadge, { type Tone } from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';

const { timeZone } = useClock();
type Message = {
    id: string;
    channel: 'email' | 'whatsapp';
    direction: 'inbound' | 'outbound';
    subject: string | null;
    body: string | null;
    status: string;
    status_label: string;
    error: string | null;
    step: number | null;
    by: string | null;
    at: string | null;
};

type Conversation = {
    messages: Message[];
    follow_up: {
        status: string;
        status_label: string;
        status_tone: Tone;
        next_step: number | null;
        next_run_at: string | null;
        last_error: string | null;
    } | null;
    engagement: {
        score: number | null;
        reason: string | null;
        scored_at: string | null;
    };
    email: {
        can_send: boolean;
        opted_out: boolean;
        sender: string | null;
        templates: {
            id: string;
            name: string;
            subject: string;
            body: string;
        }[];
    };
    whatsapp: {
        connected: boolean;
        can_send: boolean;
        opted_out: boolean;
        window_open: boolean;
        template: string | null;
    };
};

const props = defineProps<{ leadId: number }>();

const { can } = useAuthorization();

const data = ref<Conversation | null>(null);
const failed = ref(false);
const composer = ref<'email' | 'whatsapp' | null>(null);
const channel = ref<'all' | 'email' | 'whatsapp'>('all');
const sending = ref(false);

const visibleMessages = computed(() => {
    const messages = data.value?.messages ?? [];

    if (channel.value === 'all') {
        return messages;
    }

    return messages.filter((message) => message.channel === channel.value);
});

const email = ref({ template: '', subject: '', body: '' });
const whatsappText = ref('');

/**
 * Loaded on its own, so the lead page does not wait on the thread and the
 * page's controller does not need to know about messaging.
 */
async function load(): Promise<void> {
    failed.value = false;

    try {
        const response = await fetch(`/leads/${props.leadId}/conversation`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        data.value = (await response.json()) as Conversation;
    } catch {
        failed.value = true;
    }
}

onMounted(load);

const templateOptions = computed(() =>
    (data.value?.email.templates ?? []).map((t) => ({
        value: t.id,
        label: t.name,
    })),
);

function useTemplate(id: string): void {
    email.value.template = id;
    const template = data.value?.email.templates.find((t) => t.id === id);

    if (template) {
        email.value.subject = template.subject;
        email.value.body = template.body;
    }
}

function post(
    url: string,
    payload: Record<string, string>,
    after: () => void,
): void {
    sending.value = true;

    router.post(url, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            after();
            void load();
        },
        onFinish: () => (sending.value = false),
    });
}

function sendEmail(): void {
    post(
        `/leads/${props.leadId}/conversation/email`,
        { subject: email.value.subject, body: email.value.body },
        () => {
            email.value = { template: '', subject: '', body: '' };
            composer.value = null;
        },
    );
}

function sendWhatsApp(type: 'text' | 'template'): void {
    post(
        `/leads/${props.leadId}/conversation/whatsapp`,
        { type, text: whatsappText.value },
        () => {
            whatsappText.value = '';
            composer.value = null;
        },
    );
}

function stopFollowUp(): void {
    if (window.confirm('Stop the remaining follow-up emails for this lead?')) {
        post(`/leads/${props.leadId}/follow-up/stop`, {}, () => {});
    }
}

function scoreTone(score: number): Tone {
    if (score === 0) {
        return 'neutral';
    }

    return score >= 8 ? 'success' : score >= 5 ? 'info' : 'warning';
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, { timeZone: timeZone.value,
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="!data && !failed" class="space-y-2">
            <Skeleton class="h-16 w-full" />
            <Skeleton class="h-24 w-full" />
        </div>

        <div
            v-else-if="failed"
            class="rounded-xl border border-border bg-surface px-4 py-8 text-center text-[0.9rem] text-muted"
        >
            The conversation could not be loaded.
            <button
                type="button"
                class="ml-1 font-medium text-primary-600 hover:underline"
                @click="load"
            >
                Try again
            </button>
        </div>

        <template v-else-if="data">
            <!-- Where this lead stands -->
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-border bg-surface p-4">
                    <p
                        class="text-[0.78rem] font-semibold tracking-wide text-soft uppercase"
                    >
                        Reply score
                    </p>
                    <div
                        v-if="data.engagement.score !== null"
                        class="mt-1 flex items-center gap-2"
                    >
                        <span
                            class="text-[1.4rem] font-semibold text-strong tabular-nums"
                            >{{ data.engagement.score }}/10</span
                        >
                        <StatusBadge
                            :tone="scoreTone(data.engagement.score)"
                            :label="
                                data.engagement.score === 0
                                    ? 'No reply'
                                    : 'Replied'
                            "
                        />
                    </div>
                    <p v-else class="mt-1 text-[0.9rem] text-muted">
                        Not scored yet. Scored when they reply, or 0/10 if they
                        never do.
                    </p>
                    <p
                        v-if="data.engagement.reason"
                        class="mt-1 text-[0.83rem] text-muted"
                    >
                        {{ data.engagement.reason }}
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-surface p-4">
                    <p
                        class="text-[0.78rem] font-semibold tracking-wide text-soft uppercase"
                    >
                        Follow-up emails
                    </p>
                    <template v-if="data.follow_up">
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :tone="data.follow_up.status_tone"
                                :label="data.follow_up.status_label"
                            />
                            <span
                                v-if="data.follow_up.next_run_at"
                                class="text-[0.83rem] text-muted"
                            >
                                {{
                                    data.follow_up.next_step
                                        ? `Email ${data.follow_up.next_step}`
                                        : 'No-reply check'
                                }}
                                {{ formatDate(data.follow_up.next_run_at) }}
                            </span>
                        </div>
                        <p
                            v-if="data.follow_up.last_error"
                            class="mt-1 text-[0.83rem] text-danger"
                        >
                            {{ data.follow_up.last_error }}
                        </p>
                        <Button
                            v-if="
                                data.follow_up.status === 'active' &&
                                can('message.send')
                            "
                            class="mt-2"
                            variant="ghost"
                            size="sm"
                            @click="stopFollowUp"
                        >
                            <Square class="size-4" />
                            Stop follow-ups
                        </Button>
                    </template>
                    <p v-else class="mt-1 text-[0.9rem] text-muted">
                        {{
                            data.email.sender
                                ? 'Not enrolled.'
                                : 'Connect a mailbox in Settings → Email to start follow-ups.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Compose -->
            <div v-if="can('message.send')" class="flex flex-wrap gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    :disabled="!data.email.can_send"
                    :title="
                        data.email.opted_out
                            ? 'This lead unsubscribed from email'
                            : undefined
                    "
                    @click="composer = composer === 'email' ? null : 'email'"
                >
                    <Mail class="size-4" />
                    Email
                </Button>
                <Button
                    v-if="data.whatsapp.connected"
                    variant="secondary"
                    size="sm"
                    :disabled="!data.whatsapp.can_send"
                    :title="
                        data.whatsapp.opted_out
                            ? 'This lead opted out of WhatsApp'
                            : undefined
                    "
                    @click="
                        composer = composer === 'whatsapp' ? null : 'whatsapp'
                    "
                >
                    <MessageCircle class="size-4" />
                    WhatsApp
                </Button>
                <p
                    v-else
                    class="self-center text-[0.82rem] text-muted"
                >
                    <Link
                        href="/settings/integrations"
                        class="font-medium text-primary-600 hover:underline"
                    >
                        Connect WhatsApp
                    </Link>
                    to chat with this lead.
                </p>
                <p
                    v-if="data.email.opted_out"
                    class="self-center text-[0.82rem] text-muted"
                >
                    Unsubscribed from email.
                </p>
            </div>

            <form
                v-if="composer === 'email'"
                class="space-y-3 rounded-xl border border-border bg-surface p-4"
                novalidate
                @submit.prevent="sendEmail"
            >
                <FormField
                    id="compose-template"
                    label="Start from a template"
                    optional-label
                >
                    <SelectMenu
                        id="compose-template"
                        :model-value="email.template"
                        :options="templateOptions"
                        placeholder="Write it yourself"
                        @update:model-value="useTemplate"
                    />
                </FormField>
                <FormField id="compose-subject" label="Subject" required>
                    <TextInput id="compose-subject" v-model="email.subject" />
                </FormField>
                <FormField
                    id="compose-body"
                    label="Message"
                    required
                    :hint="`Sent from ${data.email.sender}`"
                >
                    <TextArea
                        id="compose-body"
                        v-model="email.body"
                        :rows="8"
                    />
                </FormField>
                <Button
                    type="submit"
                    variant="brand"
                    :loading="sending"
                    :disabled="!email.subject || !email.body"
                >
                    <Send class="size-4" />
                    Send email
                </Button>
            </form>

            <div
                v-if="composer === 'whatsapp'"
                class="space-y-3 rounded-xl border border-border bg-surface p-4"
            >
                <template v-if="data.whatsapp.window_open">
                    <FormField
                        id="compose-wa"
                        label="Message"
                        required
                        hint="They wrote within 24 hours, so you can reply freely."
                    >
                        <TextArea
                            id="compose-wa"
                            v-model="whatsappText"
                            :rows="4"
                            :maxlength="4096"
                        />
                    </FormField>
                    <Button
                        variant="brand"
                        :loading="sending"
                        :disabled="!whatsappText"
                        @click="sendWhatsApp('text')"
                    >
                        <Send class="size-4" />
                        Send
                    </Button>
                </template>
                <template v-else>
                    <p class="text-[0.88rem] text-body">
                        WhatsApp only allows free-form messages within 24 hours
                        of the lead's last message. Start the conversation with
                        your approved template; once they reply, you can chat
                        freely.
                    </p>
                    <Button
                        variant="brand"
                        :loading="sending"
                        :disabled="!data.whatsapp.template"
                        @click="sendWhatsApp('template')"
                    >
                        <Send class="size-4" />
                        Send “{{ data.whatsapp.template ?? 'no template set' }}”
                    </Button>
                </template>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in [
                        ['all', 'All'],
                        ['whatsapp', 'WhatsApp'],
                        ['email', 'Email'],
                    ]"
                    :key="option[0]"
                    type="button"
                    class="rounded-full border px-3 py-1 text-[0.8rem]"
                    :class="
                        channel === option[0]
                            ? 'border-primary-600 bg-primary-50 text-primary-700'
                            : 'border-border text-muted'
                    "
                    @click="channel = option[0] as 'all' | 'email' | 'whatsapp'"
                >
                    {{ option[1] }}
                </button>
            </div>

            <div
                v-if="visibleMessages.length === 0"
                class="rounded-xl border border-border bg-surface px-4 py-10 text-center text-[0.9rem] text-muted"
            >
                No messages yet.
            </div>

            <ol v-else class="flex flex-col gap-2">
                <li
                    v-for="message in visibleMessages"
                    :key="message.id"
                    class="max-w-[85%] rounded-2xl px-3 py-2"
                    :class="
                        message.direction === 'inbound'
                            ? 'self-start rounded-bl-md border border-border bg-surface'
                            : message.channel === 'whatsapp'
                              ? 'self-end rounded-br-md bg-primary-600 text-white'
                              : 'dark:bg-primary-950 self-end rounded-br-md border border-primary-200 bg-primary-50 dark:border-primary-900'
                    "
                >
                    <div
                        class="flex flex-wrap items-center gap-2 text-[0.72rem]"
                        :class="
                            message.direction === 'outbound' &&
                            message.channel === 'whatsapp'
                                ? 'text-white/80'
                                : 'text-muted'
                        "
                    >
                        <component
                            :is="
                                message.channel === 'email'
                                    ? Mail
                                    : MessageCircle
                            "
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        <span>{{
                            message.direction === 'inbound'
                                ? 'Lead replied'
                                : message.step
                                  ? `Follow-up ${message.step}`
                                  : (message.by ?? 'Sent')
                        }}</span>
                        <span v-if="message.direction === 'outbound'">
                            · {{ message.status_label }}
                        </span>
                    </div>
                    <p
                        v-if="message.subject"
                        class="mt-1 text-[0.88rem] font-medium"
                        :class="
                            message.direction === 'outbound' &&
                            message.channel === 'whatsapp'
                                ? 'text-white'
                                : 'text-strong'
                        "
                    >
                        {{ message.subject }}
                    </p>
                    <p class="mt-1 text-[0.88rem] whitespace-pre-line">
                        {{ message.body }}
                    </p>
                    <p
                        class="mt-1 text-[0.72rem]"
                        :class="
                            message.direction === 'outbound' &&
                            message.channel === 'whatsapp'
                                ? 'text-white/75'
                                : 'text-muted'
                        "
                    >
                        {{ formatDate(message.at) }}
                    </p>
                    <p
                        v-if="message.error"
                        class="mt-1 text-[0.8rem] text-danger"
                    >
                        {{ message.error }}
                    </p>
                </li>
            </ol>
        </template>
    </div>
</template>
