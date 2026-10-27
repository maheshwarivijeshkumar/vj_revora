<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BookOpen, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import ServiceProfileForm, {
    type Profile,
} from '@/components/settings/ServiceProfileForm.vue';
import Button from '@/components/ui/Button.vue';
import SelectMenu from '@/components/ui/SelectMenu.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{ profiles: Profile[] }>();

const { can } = useAuthorization();
const selected = ref(props.profiles[0]?.id ?? null);
const installing = ref(false);
const adding = ref(false);

const blank = useForm({ name: '', kind: 'agency' });

function install(): void {
    installing.value = true;
    router.post(
        '/settings/services/install',
        {},
        { preserveScroll: true, onFinish: () => (installing.value = false) },
    );
}

function addProfile(): void {
    blank.post('/settings/services', {
        preserveScroll: true,
        onSuccess: () => {
            adding.value = false;
            blank.reset();
        },
    });
}
</script>

<template>
    <Head title="Services" />

    <AppLayout
        title="Services"
        :breadcrumbs="[{ label: 'Settings' }, { label: 'Services' }]"
    >
        <template #actions>
            <Button
                v-if="can('settings.manage')"
                variant="secondary"
                size="sm"
                :loading="installing"
                @click="install"
            >
                <BookOpen class="size-4" />
                {{
                    profiles.length
                        ? 'Install email playbook'
                        : 'Set up from both websites'
                }}
            </Button>
        </template>

        <div class="space-y-4">
            <p class="max-w-3xl text-[0.9rem] text-muted">
                What you sell, as each business. Tenders and job posts are
                scored against these services and technologies, the
                better-fitting profile is chosen for each, and proposals are
                drafted from these facts only.
            </p>

            <div
                v-if="profiles.length === 0"
                class="rounded-xl border border-dashed border-border bg-surface px-5 py-8 text-center"
            >
                <p class="text-[0.95rem] font-medium text-strong">
                    No profiles yet
                </p>
                <p class="mx-auto mt-1 max-w-lg text-[0.87rem] text-muted">
                    Create Berg Media Digital (agency) and Vijesh Kumar
                    Maheshwari (freelancer) from bergmediadigital.com and
                    vijeshmaheshwari.com, with an email template for every stage
                    of the sales process.
                </p>
                <Button
                    v-if="can('settings.manage')"
                    class="mt-4"
                    variant="brand"
                    :loading="installing"
                    @click="install"
                >
                    Set up from both websites
                </Button>
            </div>

            <template v-else>
                <div
                    class="flex flex-wrap items-center gap-2"
                    role="tablist"
                    aria-label="Profiles"
                >
                    <button
                        v-for="profile in profiles"
                        :key="profile.id"
                        type="button"
                        role="tab"
                        :aria-selected="selected === profile.id"
                        class="rounded-full border px-4 py-1.5 text-[0.88rem] font-medium transition-colors"
                        :class="
                            selected === profile.id
                                ? 'border-primary-600 bg-primary-600 text-white'
                                : 'border-border bg-surface text-body hover:bg-surface-alt'
                        "
                        @click="selected = profile.id"
                    >
                        {{ profile.name }}
                        <span v-if="profile.is_default" class="opacity-75"
                            >· default</span
                        >
                    </button>
                    <Button
                        v-if="can('settings.manage')"
                        variant="ghost"
                        size="sm"
                        @click="adding = !adding"
                    >
                        <Plus class="size-4" />
                        Add profile
                    </Button>
                </div>

                <form
                    v-if="adding"
                    class="flex flex-wrap items-end gap-2"
                    @submit.prevent="addProfile"
                >
                    <TextInput
                        id="new-profile-name"
                        v-model="blank.name"
                        placeholder="Business name"
                        aria-label="Business name"
                        class="w-64"
                    />
                    <div class="w-56">
                        <SelectMenu
                            id="new-profile-kind"
                            v-model="blank.kind"
                            :options="[
                                { value: 'agency', label: 'Agency' },
                                { value: 'freelancer', label: 'Freelancer' },
                            ]"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="blank.processing"
                        >Add</Button
                    >
                </form>

                <section class="rounded-xl border border-border bg-surface p-5">
                    <ServiceProfileForm
                        v-for="profile in profiles.filter(
                            (p) => p.id === selected,
                        )"
                        :key="profile.id"
                        :profile="profile"
                        :can-manage="can('settings.manage')"
                    />
                </section>
            </template>
        </div>
    </AppLayout>
</template>
