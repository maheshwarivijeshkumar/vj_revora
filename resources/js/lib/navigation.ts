import type { NavSection } from '@/types/app';

/**
 * Sidebar navigation (§60, §108.1).
 *
 * Items carry two independent gates:
 *   - `permission` hides what the user is not allowed to do
 *   - `feature`    hides what the tenant's plan does not include
 *
 * Both are applied in Sidebar.vue, so the menu never offers a destination that
 * would return 403 or 402. Icon names map to Lucide components (§118) —
 * one icon library, consistently (§101.18).
 */

export const tenantNavigation: NavSection[] = [
    {
        items: [
            {
                label: 'Dashboard',
                href: '/dashboard',
                icon: 'LayoutDashboard',
                permission: 'dashboard.view',
            },
        ],
    },
    {
        label: 'CRM',
        items: [
            {
                label: 'Leads',
                href: '/leads',
                icon: 'UsersRound',
                permission: 'lead.view',
            },
            {
                label: 'Contacts',
                href: '/contacts',
                icon: 'ContactRound',
                permission: 'contact.view',
            },
            {
                label: 'Companies',
                href: '/companies',
                icon: 'Building2',
                permission: 'company.view',
            },
            {
                label: 'Deals',
                href: '/deals',
                icon: 'Handshake',
                permission: 'deal.view',
            },
            {
                label: 'Pipelines',
                href: '/pipelines',
                icon: 'GitBranch',
                permission: 'pipeline.view',
            },
        ],
    },
    {
        label: 'Engage',
        items: [
            {
                label: 'Inbox',
                href: '/inbox',
                icon: 'Inbox',
                permission: 'conversation.view',
            },
            {
                label: 'Campaigns',
                href: '/campaigns',
                icon: 'Megaphone',
                permission: 'campaign.view',
            },
            {
                label: 'Automation',
                href: '/automation',
                icon: 'Workflow',
                permission: 'automation.view',
                feature: 'automation',
            },
            {
                label: 'AI',
                href: '/ai',
                icon: 'Sparkles',
                permission: 'ai.view',
                feature: 'ai_agents',
            },
            {
                label: 'Appointments',
                href: '/appointments',
                icon: 'CalendarDays',
                permission: 'appointment.view',
            },
            {
                label: 'Tasks',
                href: '/tasks',
                icon: 'CheckSquare',
                permission: 'task.view',
            },
        ],
    },
    {
        label: 'Insight',
        items: [
            {
                label: 'Reports',
                href: '/reports',
                icon: 'ChartNoAxesCombined',
                permission: 'report.view',
            },
        ],
    },
    {
        label: 'Configure',
        items: [
            {
                label: 'Integrations',
                href: '/integrations',
                icon: 'PlugZap',
                permission: 'integration.view',
            },
            {
                label: 'Forms',
                href: '/forms',
                icon: 'FileInput',
                permission: 'form.view',
            },
            {
                label: 'Templates',
                href: '/templates',
                icon: 'LayoutTemplate',
                permission: 'template.view',
            },
            {
                label: 'API',
                href: '/settings/api-keys',
                icon: 'Braces',
                permission: 'api.view',
            },
            {
                label: 'Webhooks',
                href: '/settings/webhooks',
                icon: 'Webhook',
                permission: 'webhook.view',
            },
            {
                label: 'Audit log',
                href: '/settings/audit',
                icon: 'ScrollText',
                permission: 'audit.view',
            },
            {
                label: 'Billing',
                href: '/billing',
                icon: 'CreditCard',
                permission: 'billing.view',
            },
            {
                label: 'Settings',
                href: '/settings',
                icon: 'Settings2',
                permission: 'settings.view',
            },
        ],
    },
];

/** Platform admin navigation (§60). Served under a separate guard. */
export const platformNavigation: NavSection[] = [
    {
        items: [
            { label: 'Dashboard', href: '/platform', icon: 'LayoutDashboard' },
        ],
    },
    {
        label: 'Customers',
        items: [
            { label: 'Tenants', href: '/platform/tenants', icon: 'Building2' },
            { label: 'Users', href: '/platform/users', icon: 'UsersRound' },
        ],
    },
    {
        label: 'Commerce',
        items: [
            { label: 'Plans', href: '/platform/plans', icon: 'Layers' },
            {
                label: 'Subscriptions',
                href: '/platform/subscriptions',
                icon: 'RefreshCw',
            },
            { label: 'Billing', href: '/platform/billing', icon: 'CreditCard' },
            { label: 'Usage', href: '/platform/usage', icon: 'Gauge' },
            { label: 'Coupons', href: '/platform/coupons', icon: 'Ticket' },
            { label: 'Referrals', href: '/platform/referrals', icon: 'Share2' },
        ],
    },
    {
        label: 'Platform',
        items: [
            {
                label: 'Integrations',
                href: '/platform/integrations',
                icon: 'PlugZap',
            },
            {
                label: 'AI Providers',
                href: '/platform/ai-providers',
                icon: 'Sparkles',
            },
            {
                label: 'Message Providers',
                href: '/platform/message-providers',
                icon: 'MessageCircle',
            },
            {
                label: 'Templates',
                href: '/platform/templates',
                icon: 'LayoutTemplate',
            },
            {
                label: 'Feature Flags',
                href: '/platform/flags',
                icon: 'ToggleLeft',
            },
        ],
    },
    {
        label: 'Operations',
        items: [
            {
                label: 'System Logs',
                href: '/platform/logs',
                icon: 'ScrollText',
            },
            {
                label: 'Audit Logs',
                href: '/platform/audit',
                icon: 'ShieldCheck',
            },
            {
                label: 'Settings',
                href: '/platform/settings',
                icon: 'Settings2',
            },
        ],
    },
];
