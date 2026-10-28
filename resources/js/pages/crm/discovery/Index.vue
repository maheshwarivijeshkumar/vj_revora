<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MapPin } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectMenu, { type SelectOption } from '@/components/ui/SelectMenu.vue';
import TextInput from '@/components/ui/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Business = {
    provider: string;
    source_name: string;
    external_id: string;
    name: string;
    phone: string | null;
    website: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country_name: string | null;
    category_label: string;
    source_url: string | null;
    likely_chain: boolean;
    in_crm: boolean;
};

const props = defineProps<{
    categories: SelectOption[];
    google_ready: boolean;
    search: string;
    expired: boolean;
    query: {
        city: string;
        state: string;
        country: string;
        category: string;
        source: string;
    };
    result: {
        id: string;
        notice: string | null;
        businesses: Business[];
    } | null;
}>();

const { can } = useAuthorization();

const form = useForm({
    city: props.query.city,
    state: props.query.state,
    country: props.query.country,
    category: props.query.category,
    source: props.query.source || 'both',
});

const sources: SelectOption[] = [
    { value: 'both', label: 'Google Maps and Business Directory' },
    { value: 'google', label: 'Google Maps' },
    { value: 'directory', label: 'Business Directory' },
];

const picked = ref<string[]>([]);

const selectable = computed(() =>
    (props.result?.businesses ?? []).filter((business) => !business.in_crm),
);

watch(
    () => props.result?.id,
    () => {
        picked.value = (props.result?.businesses ?? [])
            .filter((business) => !business.in_crm && !business.likely_chain)
            .map((business) => business.external_id);
    },
    { immediate: true },
);

const importer = useForm({
    search: '',
    ids: [] as string[],
});

function runSearch(): void {
    form.post('/discovery/search');
}

function toggle(id: string, on: boolean): void {
    picked.value = on
        ? [...picked.value, id]
        : picked.value.filter((value) => value !== id);
}

function addLeads(): void {
    if (!props.result) {
        return;
    }

    importer.search = props.result.id;
    importer.ids = picked.value;
    importer.post('/discovery/import');
}

function where(business: Business): string {
    return [business.address, business.city, business.state, business.country_name]
        .filter((part) => part)
        .filter((part, index, all) => all.indexOf(part) === index)
        .join(', ');
}
</script>

<template>
    <Head title="Find businesses" />

    <AppLayout
        title="Find businesses"
        :breadcrumbs="[{ label: 'Find businesses' }]"
    >
        <div class="space-y-4">
            <div>
                <h2 class="text-h3 font-semibold text-strong">
                    Local businesses
                </h2>
                <p class="mt-1 max-w-2xl text-[0.9rem] text-muted">
                    Give a city, state and country. Matching cafés, shops,
                    clinics and other local businesses can be added as leads,
                    with the source recorded as Google Maps or Business
                    Directory.
                </p>
            </div>

            <Card>
                <form class="space-y-4" @submit.prevent="runSearch">
                    <div class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <FormField
                            id="discovery-city"
                            label="City"
                            required
                            :error="form.errors.city"
                        >
                            <TextInput
                                id="discovery-city"
                                v-model="form.city"
                                required
                                placeholder="Lahore"
                                :invalid="Boolean(form.errors.city)"
                            />
                        </FormField>
                        <FormField
                            id="discovery-state"
                            label="State"
                            optional-label
                            :error="form.errors.state"
                        >
                            <TextInput
                                id="discovery-state"
                                v-model="form.state"
                                placeholder="Punjab"
                                :invalid="Boolean(form.errors.state)"
                            />
                        </FormField>
                        <FormField
                            id="discovery-country"
                            label="Country"
                            required
                            :error="form.errors.country"
                        >
                            <TextInput
                                id="discovery-country"
                                v-model="form.country"
                                required
                                placeholder="Pakistan"
                                :invalid="Boolean(form.errors.country)"
                            />
                        </FormField>
                        <FormField
                            id="discovery-category"
                            label="Business type"
                            required
                            :error="form.errors.category"
                        >
                            <SelectMenu
                                id="discovery-category"
                                v-model="form.category"
                                :options="categories"
                                placeholder="Choose a type"
                                required
                                :invalid="Boolean(form.errors.category)"
                            />
                        </FormField>
                        <FormField
                            id="discovery-source"
                            label="Source"
                            required
                            :error="form.errors.source"
                        >
                            <SelectMenu
                                id="discovery-source"
                                v-model="form.source"
                                :options="sources"
                                required
                                :invalid="Boolean(form.errors.source)"
                            />
                        </FormField>
                    </div>

                    <p
                        v-if="!google_ready"
                        class="text-[0.85rem] text-muted"
                    >
                        Google Maps is waiting for
                        <span class="font-medium text-strong">GOOGLE_PLACES_API_KEY</span>.
                        The business directory can still search this city.
                    </p>

                    <Button type="submit" :loading="form.processing">
                        <MapPin class="size-4" />
                        Find businesses
                    </Button>
                </form>
            </Card>

            <p
                v-if="expired"
                class="text-[0.9rem] text-muted"
            >
                That search expired. Run it again.
            </p>

            <Card v-if="result" :padded="false">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
                    <div>
                        <h3 class="text-[0.98rem] font-semibold text-strong">
                            {{ result.businesses.length }} found
                        </h3>
                        <p
                            v-if="result.notice"
                            class="mt-1 max-w-xl text-[0.85rem] text-muted"
                        >
                            {{ result.notice }}
                        </p>
                    </div>
                    <Button
                        v-if="can('lead.create')"
                        type="button"
                        :disabled="picked.length === 0"
                        :loading="importer.processing"
                        @click="addLeads"
                    >
                        Add {{ picked.length }} as leads
                    </Button>
                </div>

                <p
                    v-if="result.businesses.length === 0"
                    class="px-5 py-8 text-[0.9rem] text-muted"
                >
                    Nothing matched that city and business type.
                </p>

                <div v-else class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-body">
                        <thead class="text-[0.78rem] text-muted">
                            <tr>
                                <th class="px-5 py-2 font-medium">
                                    <span class="sr-only">Add</span>
                                </th>
                                <th class="px-3 py-2 font-medium">Business</th>
                                <th class="px-3 py-2 font-medium">Source</th>
                                <th class="px-5 py-2 font-medium">Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="business in result.businesses"
                                :key="`${business.provider}-${business.external_id}`"
                                class="border-t border-border"
                            >
                                <td class="px-5 py-3">
                                    <Checkbox
                                        :id="`pick-${business.provider}-${business.external_id}`"
                                        :model-value="picked.includes(business.external_id)"
                                        :disabled="business.in_crm"
                                        @update:model-value="toggle(business.external_id, $event)"
                                    />
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-medium text-strong">
                                        {{ business.name }}
                                    </p>
                                    <p class="text-[0.8rem] text-muted">
                                        {{ business.category_label }}
                                        <template v-if="where(business)">
                                            · {{ where(business) }}
                                        </template>
                                    </p>
                                    <p
                                        v-if="business.likely_chain"
                                        class="text-[0.78rem] text-muted"
                                    >
                                        Looks like a chain. Left unchecked.
                                    </p>
                                    <p
                                        v-if="business.in_crm"
                                        class="text-[0.78rem] text-muted"
                                    >
                                        Already a lead.
                                    </p>
                                </td>
                                <td class="px-3 py-3 text-[0.85rem] text-body">
                                    <a
                                        v-if="business.source_url"
                                        :href="business.source_url"
                                        class="text-primary-600 hover:underline"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {{ business.source_name }}
                                    </a>
                                    <span v-else>{{ business.source_name }}</span>
                                </td>
                                <td class="px-5 py-3 text-[0.85rem] text-muted">
                                    <p>{{ business.phone ?? 'No public phone' }}</p>
                                    <p v-if="business.website" class="truncate">
                                        {{ business.website }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="px-5 py-4 text-[0.8rem] text-muted">
                    {{ selectable.length }} can be added.
                    <Link href="/leads" class="font-medium text-primary-600 hover:underline">
                        Open leads
                    </Link>
                    after you add them. These businesses have not asked to be
                    contacted, so nothing is emailed automatically.
                </p>
            </Card>
        </div>
    </AppLayout>
</template>
