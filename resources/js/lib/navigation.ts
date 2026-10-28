import type { NavArea, NavSection } from '@/types/app';

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

export const tenantAreas: NavArea[] = [
    {
        id: 'home',
        label: 'Home',
        icon: 'LayoutDashboard',
        sections: [
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
        ],
    },
    {
        id: 'crm',
        label: 'CRM',
        icon: 'UsersRound',
        sections: [
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
                label: 'Find businesses',
                href: '/discovery',
                icon: 'MapPin',
                permission: 'lead.view',
            },
            {
                label: 'Opportunities',
                href: '/opportunities',
                icon: 'Target',
                permission: 'lead.view',
            },
            {
                label: 'Tenders',
                href: '/tenders',
                icon: 'Landmark',
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
                label: 'Campaigns',
                href: '/campaigns',
                icon: 'Megaphone',
                permission: 'campaign.view',
            },
            {
                label: 'Calendar',
                href: '/calendar',
                icon: 'CalendarDays',
                permission: 'lead.view',
            },
                ],
            },
        ],
    },
    {
        id: 'work',
        label: 'Work',
        icon: 'FolderKanban',
        sections: [
            {
                label: 'Work',
                items: [
            {
                label: 'Projects',
                href: '/projects',
                icon: 'FolderKanban',
                permission: 'company.view',
            },
            {
                label: 'Schedule',
                href: '/schedule',
                icon: 'CalendarClock',
                permission: 'task.view',
            },
            {
                label: 'Clients',
                href: '/clients',
                icon: 'UserRoundCheck',
                permission: 'company.view',
            },
            {
                label: 'Estimates',
                href: '/estimates',
                icon: 'FileSpreadsheet',
                permission: 'quote.view',
            },
            {
                label: 'Invoices',
                href: '/invoices',
                icon: 'Receipt',
                permission: 'billing.view',
            },
            {
                label: 'Crew',
                href: '/crew',
                icon: 'HardHat',
                permission: 'company.view',
            },
            {
                label: 'Subcontractors',
                href: '/subcontractors',
                icon: 'Truck',
                permission: 'company.view',
            },
            {
                label: 'Materials',
                href: '/materials',
                icon: 'Package',
                permission: 'product.view',
            },
            {
                label: 'Change orders',
                href: '/change-orders',
                icon: 'FileDiff',
                permission: 'quote.view',
            },
            {
                label: 'Documents',
                href: '/documents',
                icon: 'Files',
                permission: 'document.view',
            },
            {
                label: 'Messages',
                href: '/messages',
                icon: 'Inbox',
                permission: 'conversation.view',
            },
            {
                label: 'Reports',
                href: '/reports',
                icon: 'ChartNoAxesCombined',
                permission: 'report.view',
            },
                ],
            },
        ],
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: 'Settings2',
        sections: [
            {
                label: 'Workspace',
                items: [
                    {
                        label: 'Setup',
                        href: '/settings/setup',
                        icon: 'ListChecks',
                        permission: 'settings.view',
                    },
                    {
                        label: 'Services',
                        href: '/settings/services',
                        icon: 'BriefcaseBusiness',
                        permission: 'settings.view',
                    },
                    {
                        label: 'Integrations',
                        href: '/settings/integrations',
                        icon: 'PlugZap',
                        permission: 'integration.view',
                    },
                ],
            },
            {
                label: 'Channels',
                items: [
                    {
                        label: 'Email',
                        href: '/settings/email',
                        icon: 'Mail',
                        permission: 'integration.view',
                    },
                    {
                        label: 'Follow-up',
                        href: '/settings/follow-up',
                        icon: 'Send',
                        permission: 'template.view',
                    },
                ],
            },
            {
                label: 'Developer',
                items: [
                    {
                        label: 'API tokens',
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
                ],
            },
        ],
    },
];

/** Every section, for callers that do not care which rail icon owns it. */
export const tenantNavigation: NavSection[] = tenantAreas.flatMap(
    (area) => area.sections,
);

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
