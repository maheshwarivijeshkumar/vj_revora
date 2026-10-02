<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Starter plan set.
 *
 * These are example *data*, not a contract. Prices and limits are expected to
 * change from the admin UI without a deploy — nothing in application code may
 * branch on a plan key (§8, §101.28).
 *
 * `null` in a limit means unlimited.
 */
final class PlanSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, price: float, trial: int, features: array<string, int|bool|null>}>
     */
    private const PLANS = [
        'starter' => [
            'name' => 'Starter',
            'price' => 49.0,
            'trial' => 14,
            'features' => [
                'users' => 3, 'teams' => 1, 'contacts' => 2_500, 'companies' => 1_000,
                'storage' => 2_048, 'custom_fields' => 10, 'pipelines' => 1,
                'dashboard_widgets' => 8, 'leads_per_month' => 1_000,
                'whatsapp_messages' => 500, 'email_messages' => 5_000,
                'sms_messages' => 250, 'automation_runs' => 1_000,
                'ai_credits' => 500, 'ai_tokens' => 250_000, 'api_requests' => 10_000,
                'automation' => true, 'ai_agents' => false,
                'social_connections' => 2, 'ad_accounts' => 1, 'integrations' => 3,
                'webhooks' => 2, 'reports' => 5,
                'crm_connectors' => false, 'white_label' => false, 'advanced_analytics' => false,
            ],
        ],
        'growth' => [
            'name' => 'Growth',
            'price' => 149.0,
            'trial' => 14,
            'features' => [
                'users' => 10, 'teams' => 5, 'contacts' => 25_000, 'companies' => 10_000,
                'storage' => 20_480, 'custom_fields' => 50, 'pipelines' => 5,
                'dashboard_widgets' => 25, 'leads_per_month' => 10_000,
                'whatsapp_messages' => 5_000, 'email_messages' => 50_000,
                'sms_messages' => 2_500, 'automation_runs' => 25_000,
                'ai_credits' => 5_000, 'ai_tokens' => 5_000_000, 'api_requests' => 250_000,
                'automation' => true, 'ai_agents' => true,
                'social_connections' => 10, 'ad_accounts' => 5, 'integrations' => 15,
                'webhooks' => 10, 'reports' => 50,
                'crm_connectors' => true, 'white_label' => false, 'advanced_analytics' => true,
            ],
        ],
        'scale' => [
            'name' => 'Scale',
            'price' => 499.0,
            'trial' => 14,
            'features' => [
                'users' => null, 'teams' => null, 'contacts' => null, 'companies' => null,
                'storage' => 204_800, 'custom_fields' => null, 'pipelines' => null,
                'dashboard_widgets' => null, 'leads_per_month' => 100_000,
                'whatsapp_messages' => 50_000, 'email_messages' => 500_000,
                'sms_messages' => 25_000, 'automation_runs' => null,
                'ai_credits' => 50_000, 'ai_tokens' => 50_000_000, 'api_requests' => null,
                'automation' => true, 'ai_agents' => true,
                'social_connections' => null, 'ad_accounts' => null, 'integrations' => null,
                'webhooks' => null, 'reports' => null,
                'crm_connectors' => true, 'white_label' => true, 'advanced_analytics' => true,
            ],
        ],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::PLANS as $key => $definition) {
            $plan = Plan::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'interval' => 'monthly',
                    'price' => $definition['price'],
                    'currency' => 'USD',
                    'trial_days' => $definition['trial'],
                    'is_public' => true,
                    'is_active' => true,
                    'sort_order' => $order++,
                ],
            );

            foreach ($definition['features'] as $featureKey => $value) {
                // A null limit on a granted feature means unlimited, not absent —
                // is_unlimited carries that so `value` stays purely quantitative.
                $unlimited = $value === null;

                $plan->features()->updateOrCreate(
                    ['feature_key' => $featureKey],
                    [
                        'value' => $unlimited ? true : $value,
                        'is_unlimited' => $unlimited,
                    ],
                );
            }
        }
    }
}
