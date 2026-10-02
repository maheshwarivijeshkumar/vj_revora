<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The feature catalog from §8 — the vocabulary plans are built from.
 *
 * `limit` features are capped quantities, `metered` ones are consumed and may
 * be billed as overage, `boolean` ones are simple capability switches.
 */
final class FeatureCatalogSeeder extends Seeder
{
    /**
     * @var list<array{key: string, name: string, type: string, unit: string|null, category: string}>
     */
    private const FEATURES = [
        // Capacity
        ['key' => 'users', 'name' => 'Users', 'type' => 'limit', 'unit' => 'seats', 'category' => 'capacity'],
        ['key' => 'teams', 'name' => 'Teams', 'type' => 'limit', 'unit' => 'teams', 'category' => 'capacity'],
        ['key' => 'contacts', 'name' => 'Contacts', 'type' => 'limit', 'unit' => 'records', 'category' => 'capacity'],
        ['key' => 'companies', 'name' => 'Companies', 'type' => 'limit', 'unit' => 'records', 'category' => 'capacity'],
        ['key' => 'storage', 'name' => 'Storage', 'type' => 'limit', 'unit' => 'MB', 'category' => 'capacity'],
        ['key' => 'custom_fields', 'name' => 'Custom Fields', 'type' => 'limit', 'unit' => 'fields', 'category' => 'capacity'],
        ['key' => 'pipelines', 'name' => 'Pipelines', 'type' => 'limit', 'unit' => 'pipelines', 'category' => 'capacity'],
        ['key' => 'dashboard_widgets', 'name' => 'Dashboard Widgets', 'type' => 'limit', 'unit' => 'widgets', 'category' => 'capacity'],

        // Metered consumption
        ['key' => 'leads_per_month', 'name' => 'Leads per Month', 'type' => 'metered', 'unit' => 'leads', 'category' => 'usage'],
        ['key' => 'whatsapp_messages', 'name' => 'WhatsApp Messages', 'type' => 'metered', 'unit' => 'messages', 'category' => 'usage'],
        ['key' => 'email_messages', 'name' => 'Email Messages', 'type' => 'metered', 'unit' => 'messages', 'category' => 'usage'],
        ['key' => 'sms_messages', 'name' => 'SMS Messages', 'type' => 'metered', 'unit' => 'messages', 'category' => 'usage'],
        ['key' => 'automation_runs', 'name' => 'Automation Runs', 'type' => 'metered', 'unit' => 'runs', 'category' => 'usage'],
        ['key' => 'ai_credits', 'name' => 'AI Credits', 'type' => 'metered', 'unit' => 'credits', 'category' => 'usage'],
        ['key' => 'ai_tokens', 'name' => 'AI Tokens', 'type' => 'metered', 'unit' => 'tokens', 'category' => 'usage'],
        ['key' => 'api_requests', 'name' => 'API Requests', 'type' => 'metered', 'unit' => 'requests', 'category' => 'usage'],

        // Capabilities
        ['key' => 'automation', 'name' => 'Automation Workflows', 'type' => 'boolean', 'unit' => null, 'category' => 'features'],
        ['key' => 'ai_agents', 'name' => 'AI Sales Agent', 'type' => 'boolean', 'unit' => null, 'category' => 'features'],
        ['key' => 'social_connections', 'name' => 'Social Integrations', 'type' => 'limit', 'unit' => 'connections', 'category' => 'features'],
        ['key' => 'ad_accounts', 'name' => 'Ad Accounts', 'type' => 'limit', 'unit' => 'accounts', 'category' => 'features'],
        ['key' => 'integrations', 'name' => 'Integrations', 'type' => 'limit', 'unit' => 'integrations', 'category' => 'features'],
        ['key' => 'webhooks', 'name' => 'Webhooks', 'type' => 'limit', 'unit' => 'endpoints', 'category' => 'features'],
        ['key' => 'reports', 'name' => 'Reports', 'type' => 'limit', 'unit' => 'reports', 'category' => 'features'],
        ['key' => 'crm_connectors', 'name' => 'External CRM Sync', 'type' => 'boolean', 'unit' => null, 'category' => 'features'],
        ['key' => 'white_label', 'name' => 'White Label', 'type' => 'boolean', 'unit' => null, 'category' => 'features'],
        ['key' => 'advanced_analytics', 'name' => 'Advanced Analytics', 'type' => 'boolean', 'unit' => null, 'category' => 'features'],
    ];

    public function run(): void
    {
        foreach (self::FEATURES as $i => $feature) {
            DB::table('feature_catalog')->updateOrInsert(
                ['key' => $feature['key']],
                [
                    'name' => $feature['name'],
                    'type' => $feature['type'],
                    'unit' => $feature['unit'],
                    'category' => $feature['category'],
                    'sort_order' => $i,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
