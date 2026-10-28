<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Megaphone, RefreshCw } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthorization } from '@/composables/useAuthorization';

type Campaign = {
    id: number;
    platform: string;
    platform_label: string;
    name: string;
    status: string | null;
    objective: string | null;
    starts_at: string | null;
    ends_at: string | null;
    account: string | null;
};

type Platform = { value: string; label: string };

const props = defineProps<{
    campaigns: Campaign[];
    platforms: Platform[];
    filters: { platform: string };
}>();

const { can } = useAuthorization();

function show(platform: string): void {
    router.get('/campaigns', platform === '' ? {} : { platform }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function sync(): void {
    router.post('/campaigns/sync', {}, { preserveScroll: true });
}

function dates(campaign: Campaign): string {
    if (!campaign.starts_at && !campaign.ends_at) {
        return '—';
    }

    return [campaign.starts_at, campaign.ends_at].filter(Boolean).join(' – ');
}
</script>

<template>
    <Head title="Campaigns" />

    <AppLayout title="Campaigns" :breadcrumbs="[{ label: 'Campaigns' }]">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <p class="max-w-3xl text-[0.88rem] text-muted">
                    Facebook and Instagram come from each connected Facebook
                    Page. LinkedIn comes from each connected ad account. Google,
                    TikTok and any other network appear when a lead already
                    names that campaign. The list refreshes every hour.
                </p>
                <Button
                    v-if="can('campaign.manage')"
                    type="button"
                    variant="secondary"
                    @click="sync"
                >
                    <RefreshCw class="size-4" />
                    Sync now
                </Button>
            </div>

            <div v-if="platforms.length" class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-small"
                    :class="
                        filters.platform === ''
                            ? 'border-primary-600 bg-primary-50 text-primary-700'
                            : 'border-border text-muted'
                    "
                    @click="show('')"
                >
                    All
                </button>
                <button
                    v-for="platform in platforms"
                    :key="platform.value"
                    type="button"
                    class="rounded-full border px-3 py-1 text-small"
                    :class="
                        filters.platform === platform.value
                            ? 'border-primary-600 bg-primary-50 text-primary-700'
                            : 'border-border text-muted'
                    "
                    @click="show(platform.value)"
                >
                    {{ platform.label }}
                </button>
            </div>

            <EmptyState
                v-if="campaigns.length === 0"
                title="No campaigns yet"
                description="Connect Facebook or LinkedIn under Integrations, or capture a lead that names its campaign. Google and TikTok show up that way until those accounts are connected."
            >
                <template #icon>
                    <Megaphone class="size-5" />
                </template>
                <template #actions>
                    <Link href="/settings/integrations">
                        <Button type="button" variant="secondary">Integrations</Button>
                    </Link>
                </template>
            </EmptyState>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <table class="w-full text-left text-body">
                    <thead class="border-b border-border text-small text-muted">
                        <tr>
                            <th class="px-4 py-3 font-medium">Campaign</th>
                            <th class="px-4 py-3 font-medium">Platform</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Dates</th>
                            <th class="px-4 py-3 font-medium">Account</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="campaign in campaigns"
                            :key="campaign.id"
                            class="border-b border-border last:border-0"
                        >
                            <td class="px-4 py-3 text-strong">
                                {{ campaign.name }}
                                <span
                                    v-if="campaign.objective"
                                    class="mt-0.5 block text-small text-muted"
                                >
                                    {{ campaign.objective }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ campaign.platform_label }}</td>
                            <td class="px-4 py-3">{{ campaign.status ?? '—' }}</td>
                            <td class="px-4 py-3">{{ dates(campaign) }}</td>
                            <td class="px-4 py-3">{{ campaign.account ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
