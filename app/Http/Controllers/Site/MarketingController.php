<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\Branding\Brand;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing site (§124).
 *
 * Shares the application's design language but is more expressive. Copy stays
 * claim-free: no invented customer logos, testimonials or performance figures,
 * which §124 prohibits outright. Every number on these pages either comes from
 * the database or is not shown.
 */
final class MarketingController extends Controller
{
    public function __construct(
        private readonly Brand $brand,
    ) {}

    public function home(): Response
    {
        return $this->page('marketing/Home', ['plans' => $this->plans()]);
    }

    public function product(): Response
    {
        return $this->page('marketing/Product');
    }

    public function solutions(): Response
    {
        return $this->page('marketing/Solutions');
    }

    public function integrations(): Response
    {
        return $this->page('marketing/Integrations');
    }

    public function pricing(): Response
    {
        return $this->page('marketing/Pricing', [
            'plans' => $this->plans(),
            'matrix' => $this->matrix(),
        ]);
    }

    public function security(): Response
    {
        return $this->page('marketing/Security');
    }

    public function developers(): Response
    {
        return $this->page('marketing/Developers');
    }

    public function about(): Response
    {
        return $this->page('marketing/About');
    }

    public function contact(): Response
    {
        return $this->page('marketing/Contact');
    }

    /**
     * Every marketing page needs brand, contact and social details for the
     * shared shell, so they are added once here rather than per action.
     *
     * @param  array<string, mixed>  $props
     */
    private function page(string $component, array $props = []): Response
    {
        return Inertia::render($component, [
            'brand' => $this->brand->current(),
            'contact' => array_filter((array) config('site.contact')),
            'social' => array_filter((array) config('site.social')),
            ...$props,
        ]);
    }

    /**
     * Pricing read from the plans table, never hard-coded (§8, §101.28).
     *
     * A price change is a data change; these pages follow automatically.
     *
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        $plans = Plan::query()
            ->with('features')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();

        // Accumulated rather than mapped so the result is a list and
        // serialises to a JSON array.
        $result = [];

        foreach ($plans as $plan) {
            $result[] = [
                'key' => $plan->key,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'trial_days' => $plan->trial_days,
                'highlights' => $this->highlights($plan, self::HEADLINE_FEATURES),
            ];
        }

        return $result;
    }

    /**
     * Shown on a pricing card. Deliberately a fixed, ordered subset so the
     * cards compare like with like: a plan missing one of these shows it as
     * absent rather than quietly promoting a different feature into the slot.
     */
    private const HEADLINE_FEATURES = [
        'users' => 'Users',
        'leads_per_month' => 'Leads / month',
        'whatsapp_messages' => 'WhatsApp messages',
        'email_messages' => 'Emails',
        'automation' => 'Automation workflows',
        'ai_agents' => 'AI sales agent',
        'crm_connectors' => 'External CRM sync',
        'white_label' => 'White label',
    ];

    /**
     * The full comparison table on the pricing page, grouped the way someone
     * evaluating plans actually reads them.
     *
     * @return list<array{group: string, rows: list<array{label: string, values: list<array{value: string, included: bool}>}>}>
     */
    private function matrix(): array
    {
        $groups = [
            'Capacity' => [
                'users' => 'Users',
                'teams' => 'Teams',
                'contacts' => 'Contacts',
                'companies' => 'Companies',
                'pipelines' => 'Pipelines',
                'custom_fields' => 'Custom fields',
                'storage' => 'Storage (MB)',
            ],
            'Volume' => [
                'leads_per_month' => 'Leads per month',
                'whatsapp_messages' => 'WhatsApp messages',
                'email_messages' => 'Emails',
                'sms_messages' => 'SMS',
                'automation_runs' => 'Automation runs',
                'api_requests' => 'API requests',
            ],
            'Intelligence' => [
                'ai_credits' => 'AI credits',
                'ai_tokens' => 'AI tokens',
                'ai_agents' => 'AI sales agent',
                'advanced_analytics' => 'Advanced analytics',
            ],
            'Platform' => [
                'automation' => 'Automation workflows',
                'social_connections' => 'Social connections',
                'ad_accounts' => 'Ad accounts',
                'integrations' => 'Integrations',
                'webhooks' => 'Webhooks',
                'reports' => 'Reports',
                'dashboard_widgets' => 'Dashboard widgets',
                'crm_connectors' => 'External CRM sync',
                'white_label' => 'White label',
            ],
        ];

        $plans = Plan::query()
            ->with('features')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();

        $matrix = [];

        foreach ($groups as $group => $keys) {
            $rows = [];

            foreach ($keys as $key => $label) {
                $values = [];

                foreach ($plans as $plan) {
                    $values[] = $this->cell($plan, $key);
                }

                $rows[] = ['label' => $label, 'values' => $values];
            }

            $matrix[] = ['group' => $group, 'rows' => $rows];
        }

        return $matrix;
    }

    /**
     * @param  array<string, string>  $keys
     * @return list<array{label: string, value: string, included: bool}>
     */
    private function highlights(Plan $plan, array $keys): array
    {
        $highlights = [];

        foreach ($keys as $key => $label) {
            $cell = $this->cell($plan, $key);

            $highlights[] = [
                'label' => $label,
                'value' => $cell['value'],
                'included' => $cell['included'],
            ];
        }

        return $highlights;
    }

    /**
     * @return array{value: string, included: bool}
     */
    private function cell(Plan $plan, string $key): array
    {
        $feature = $plan->feature($key);

        if ($feature === null || ! $feature->isEnabled()) {
            return ['value' => '—', 'included' => false];
        }

        $limit = $feature->limit();

        // limit() returns null both for an unlimited quota and for a boolean
        // capability, so is_unlimited has to disambiguate. Labelling "AI sales
        // agent" as Unlimited rather than Included implies a quota that does
        // not exist.
        return [
            'value' => match (true) {
                $feature->is_unlimited => 'Unlimited',
                $limit !== null => number_format($limit),
                default => 'Included',
            },
            'included' => true,
        ];
    }
}
