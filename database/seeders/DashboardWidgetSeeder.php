<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard widget catalog (§36, §80, §109).
 *
 * This table is what makes dashboards data-driven rather than hard-coded
 * (§101.29) — it is the registry the Add Widget picker reads.
 */
final class DashboardWidgetSeeder extends Seeder
{
    /**
     * key, name, type, category, permission
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private const WIDGETS = [
        ['lead_overview', 'Lead Overview', 'kpi', 'Lead Metrics', 'lead.view'],
        ['lead_funnel', 'Lead Funnel', 'funnel', 'Lead Funnel', 'lead.view'],
        ['lead_sources', 'Lead Sources', 'donut', 'Lead Sources', 'lead.view'],
        ['lead_trend', 'Lead Trend', 'line', 'Lead Metrics', 'lead.view'],
        ['lead_quality', 'Lead Quality', 'bar', 'Lead Quality', 'lead.view'],
        ['ai_qualification', 'AI Qualification', 'kpi', 'AI Qualification', 'ai.view'],
        ['conversion_rate', 'Conversion Rate', 'kpi', 'Conversion', 'report.view'],
        ['revenue', 'Revenue', 'area', 'Revenue', 'report.view'],
        ['pipeline', 'Pipeline', 'bar', 'Pipeline', 'deal.view'],
        ['deals', 'Deals', 'table', 'Deals', 'deal.view'],
        ['sales_leaderboard', 'Sales Leaderboard', 'table', 'Team Performance', 'report.view'],
        ['recent_leads', 'Recent Leads', 'table', 'Lead Metrics', 'lead.view'],
        ['recent_conversations', 'Recent Conversations', 'list', 'Conversion', 'conversation.view'],
        ['upcoming_appointments', 'Upcoming Appointments', 'list', 'Appointments', 'appointment.view'],
        ['calendar', 'Calendar', 'calendar', 'Appointments', 'appointment.view'],
        ['tasks', 'Tasks', 'list', 'Tasks', 'task.view'],
        ['campaign_performance', 'Campaign Performance', 'bar', 'Campaigns', 'campaign.view'],
        ['whatsapp_performance', 'WhatsApp Performance', 'kpi', 'WhatsApp', 'conversation.view'],
        ['email_performance', 'Email Performance', 'kpi', 'Email', 'conversation.view'],
        ['sms_performance', 'SMS Performance', 'kpi', 'SMS', 'conversation.view'],
        ['ai_performance', 'AI Performance', 'kpi', 'AI Qualification', 'ai.view'],
        ['automation_health', 'Automation Health', 'kpi', 'Automation', 'automation.view'],
        ['response_time', 'Response Time', 'line', 'Response Time', 'report.view'],
        ['sla_compliance', 'SLA Compliance', 'gauge', 'SLA', 'report.view'],
        ['attribution', 'Revenue Attribution', 'bar', 'Attribution', 'report.view'],
        ['integration_health', 'Integration Health', 'status', 'Integration Health', 'integration.view'],
        ['subscription_usage', 'Subscription Usage', 'progress', 'Subscription Usage', 'billing.view'],
        ['api_usage', 'API Usage', 'line', 'API Usage', 'api.view'],
    ];

    public function run(): void
    {
        foreach (self::WIDGETS as $i => [$key, $name, $type, $category, $permission]) {
            DB::table('dashboard_widgets')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $name,
                    'type' => $type,
                    'category' => $category,
                    'permission' => $permission,
                    'configurable' => true,
                    'refreshable' => true,
                    'date_filter' => true,
                    'default_size' => json_encode(['width' => 3, 'height' => 2]),
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
