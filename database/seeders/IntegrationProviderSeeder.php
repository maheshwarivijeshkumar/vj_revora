<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The integration provider registry (§10, §76).
 *
 * All providers ship disabled. Each is enabled from platform admin only once
 * its developer-program approval and credentials are actually in place, so the
 * UI can never offer a connection that cannot complete.
 *
 * api_version lives here rather than in code, so a provider version bump is a
 * configuration change (§76, §101.9).
 *
 * Verify each provider's current documentation before implementing it — API
 * versions, permissions and product availability change (§103).
 */
final class IntegrationProviderSeeder extends Seeder
{
    /**
     * key, name, category, api_version, docs_url
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string|null, 4: string|null}>
     */
    private const PROVIDERS = [
        ['meta', 'Facebook / Meta', 'social', 'v21.0', 'https://developers.facebook.com/docs/marketing-api/guides/lead-ads'],
        ['instagram', 'Instagram', 'social', 'v21.0', 'https://developers.facebook.com/docs/instagram-platform'],
        ['tiktok', 'TikTok', 'social', 'v1.3', 'https://developers.tiktok.com/doc/obtain-access-token-for-apis'],
        ['linkedin', 'LinkedIn', 'social', '202501', 'https://learn.microsoft.com/en-us/linkedin/marketing/lead-sync/leadsync-overview'],
        ['google_ads', 'Google Ads', 'ads', 'v18', 'https://developers.google.com/google-ads/api/docs/start'],
        ['whatsapp', 'WhatsApp Business', 'messaging', 'v21.0', 'https://developers.facebook.com/docs/whatsapp/cloud-api'],
        ['google_calendar', 'Google Calendar', 'calendar', 'v3', 'https://developers.google.com/calendar/api'],
        ['microsoft_calendar', 'Microsoft Calendar', 'calendar', 'v1.0', 'https://learn.microsoft.com/en-us/graph/api/resources/calendar'],
        ['custom_webhook', 'Custom Webhook', 'custom', null, null],
    ];

    public function run(): void
    {
        foreach (self::PROVIDERS as [$key, $name, $category, $version, $docs]) {
            DB::table('integration_providers')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $name,
                    'category' => $category,
                    'api_version' => $version,
                    'docs_url' => $docs,
                    'is_enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
