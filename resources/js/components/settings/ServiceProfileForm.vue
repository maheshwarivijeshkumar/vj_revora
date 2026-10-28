<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextInput from '@/components/ui/TextInput.vue';

export type Profile = {
    id: string;
    name: string;
    kind: 'agency' | 'freelancer';
    tagline: string | null;
    website: string | null;
    email: string | null;
    phone: string | null;
    location: string | null;
    about: string | null;
    services: { name: string; summary: string; keywords: string }[];
    skills: string;
    portfolio: { name: string; summary?: string; url?: string | null }[];
    markets: string;
    pricing: string | null;
    signature: string | null;
    is_default: boolean;
};

const props = defineProps<{ profile: Profile; canManage: boolean }>();

const form = useForm({
    name: props.profile.name,
    kind: props.profile.kind,
    tagline: props.profile.tagline ?? '',
    website: props.profile.website ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    location: props.profile.location ?? '',
    about: props.profile.about ?? '',
    services: props.profile.services.map((s) => ({ ...s })),
    skills: props.profile.skills,
    portfolio: props.profile.portfolio.map((p) => ({
        name: p.name,
        summary: p.summary ?? '',
        url: p.url ?? '',
    })),
    markets: props.profile.markets,
    pricing: props.profile.pricing ?? '',
    signature: props.profile.signature ?? '',
    is_default: props.profile.is_default,
});

const kinds = [
    { value: 'agency', label: 'Agency (writes as “we”)' },
    { value: 'freelancer', label: 'Freelancer (writes as “I”)' },
];

function save(): void {
    form.put(`/settings/services/${props.profile.id}`, {
        preserveScroll: true,
    });
}

function remove(): void {
    if (window.confirm(`Remove the ${props.profile.name} profile?`)) {
        router.delete(`/settings/services/${props.profile.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField
                :id="`${profile.id}-name`"
                label="Business name"
                required
                :error="form.errors.name"
            >
                <TextInput :id="`${profile.id}-name`" v-model="form.name" />
            </FormField>
            <FormField :id="`${profile.id}-kind`" label="Type" required>
                <SelectMenu
                    :id="`${profile.id}-kind`"
                    v-model="form.kind"
                    :options="kinds"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-tagline`"
                label="Tagline"
                optional-label
            >
                <TextInput
                    :id="`${profile.id}-tagline`"
                    v-model="form.tagline"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-website`"
                label="Website"
                optional-label
                :error="form.errors.website"
            >
                <TextInput
                    :id="`${profile.id}-website`"
                    v-model="form.website"
                    type="url"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-email`"
                label="Email"
                optional-label
                :error="form.errors.email"
            >
                <TextInput
                    :id="`${profile.id}-email`"
                    v-model="form.email"
                    type="email"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-phone`"
                label="Phone / WhatsApp"
                optional-label
            >
                <TextInput
                    :id="`${profile.id}-phone`"
                    v-model="form.phone"
                    type="tel"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-location`"
                label="Location"
                optional-label
            >
                <TextInput
                    :id="`${profile.id}-location`"
                    v-model="form.location"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-markets`"
                label="Markets"
                optional-label
                hint="Comma separated, e.g. United Kingdom, UAE, Pakistan"
            >
                <TextInput
                    :id="`${profile.id}-markets`"
                    v-model="form.markets"
                />
            </FormField>
        </div>

        <FormField
            :id="`${profile.id}-about`"
            label="About, experience and resume"
            optional-label
            hint="Proposals are written only from what is here. For a freelancer, paste the full resume."
            :error="form.errors.about"
        >
            <TextArea
                :id="`${profile.id}-about`"
                v-model="form.about"
                :rows="8"
            />
        </FormField>

        <fieldset>
            <legend class="text-[0.88rem] font-medium text-strong">
                Services
            </legend>
            <div class="mt-1.5 space-y-2">
                <div
                    v-for="(service, i) in form.services"
                    :key="i"
                    class="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[14rem_1fr_auto]"
                >
                    <TextInput
                        :id="`${profile.id}-svc-${i}-name`"
                        v-model="service.name"
                        placeholder="Service name"
                        aria-label="Service name"
                    />
                    <div class="space-y-2">
                        <TextInput
                            :id="`${profile.id}-svc-${i}-summary`"
                            v-model="service.summary"
                            placeholder="One-line summary"
                            aria-label="Summary"
                        />
                        <TextInput
                            :id="`${profile.id}-svc-${i}-keywords`"
                            v-model="service.keywords"
                            placeholder="Keywords, comma separated"
                            aria-label="Keywords"
                            :invalid="
                                Boolean(form.errors[`services.${i}.keywords`])
                            "
                        />
                        <p
                            v-if="form.errors[`services.${i}.keywords`]"
                            class="text-[0.8rem] text-danger"
                        >
                            {{ form.errors[`services.${i}.keywords`] }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`Remove ${service.name}`"
                        @click="form.services.splice(i, 1)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
            <Button
                class="mt-2"
                variant="secondary"
                size="sm"
                @click="
                    form.services.push({ name: '', summary: '', keywords: '' })
                "
            >
                <Plus class="size-4" />
                Add a service
            </Button>
            <p class="mt-1.5 text-[0.82rem] leading-snug text-muted">
                Keywords decide which tenders and jobs match. Use the words
                clients write, e.g. “website”, “mobile app”, “ERP”.
            </p>
            <p
                v-if="form.errors.services"
                role="alert"
                class="mt-1.5 text-[0.85rem] leading-snug text-danger"
            >
                {{ form.errors.services }}
            </p>
        </fieldset>

        <FormField
            :id="`${profile.id}-skills`"
            label="Technologies"
            optional-label
            hint="Comma separated. A job that names these scores higher."
        >
            <TextInput :id="`${profile.id}-skills`" v-model="form.skills" />
        </FormField>

        <fieldset>
            <legend class="text-[0.88rem] font-medium text-strong">
                Portfolio and case studies
            </legend>
            <div class="mt-2 space-y-2">
                <div
                    v-for="(item, i) in form.portfolio"
                    :key="i"
                    class="grid gap-2 sm:grid-cols-[12rem_1fr_14rem_auto]"
                >
                    <TextInput
                        :id="`${profile.id}-pf-${i}-name`"
                        v-model="item.name"
                        placeholder="Project"
                        aria-label="Project name"
                    />
                    <TextInput
                        :id="`${profile.id}-pf-${i}-summary`"
                        v-model="item.summary"
                        placeholder="What it is and the result"
                        aria-label="Summary"
                    />
                    <TextInput
                        :id="`${profile.id}-pf-${i}-url`"
                        v-model="item.url"
                        type="url"
                        placeholder="https://"
                        aria-label="Link"
                    />
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`Remove ${item.name}`"
                        @click="form.portfolio.splice(i, 1)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
            <Button
                class="mt-2"
                variant="secondary"
                size="sm"
                @click="form.portfolio.push({ name: '', summary: '', url: '' })"
            >
                <Plus class="size-4" />
                Add a project
            </Button>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <FormField
                :id="`${profile.id}-pricing`"
                label="Pricing"
                optional-label
                hint="Only mentioned in proposals if filled in."
            >
                <TextArea
                    :id="`${profile.id}-pricing`"
                    v-model="form.pricing"
                    :rows="4"
                />
            </FormField>
            <FormField
                :id="`${profile.id}-signature`"
                label="Sign-off"
                optional-label
                hint="Used as {{my_signature}} in templates."
            >
                <TextArea
                    :id="`${profile.id}-signature`"
                    v-model="form.signature"
                    :rows="4"
                />
            </FormField>
        </div>

        <label class="flex items-center gap-2 text-[0.88rem] text-strong">
            <Checkbox :id="`${profile.id}-default`" v-model="form.is_default" />
            Default profile: signs the automatic follow-up emails
        </label>

        <div v-if="canManage" class="flex gap-2">
            <Button
                type="submit"
                variant="brand"
                size="lg"
                :loading="form.processing"
                >Save and re-score</Button
            >
            <Button
                type="button"
                variant="danger"
                size="icon"
                :aria-label="`Remove ${profile.name}`"
                @click="remove"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>
    </form>
</template>
