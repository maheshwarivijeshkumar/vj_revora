/**
 * Application-wide types mirroring what HandleInertiaRequests shares.
 *
 * Kept in sync with app/Http/Middleware/HandleInertiaRequests.php — when a
 * shared prop changes there, it changes here.
 */

export type Theme = 'light' | 'dark' | 'system';

export type TenantStatus =
    | 'provisioning'
    | 'active'
    | 'suspended'
    | 'cancelled';

export type Tenant = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    status: TenantStatus;
};

/**
 * One feature's entitlement state. `limit: null` with `enabled: true` means
 * unlimited, not ungranted — see Entitlements::snapshot().
 */
export type Entitlement = {
    enabled: boolean;
    limit: number | null;
    used: number;
    remaining: number | null;
};

export type EntitlementMap = Record<string, Entitlement>;

export type ToastType = 'success' | 'info' | 'warning' | 'error' | 'loading';

export type ToastAction = {
    label: string;
    handler: () => void;
};

export type Toast = {
    id: string;
    type: ToastType;
    title: string;
    description?: string;
    /** Milliseconds; 0 keeps the toast until dismissed. */
    duration: number;
    action?: ToastAction;
    /**
     * Optional second choice, rendered quieter than `action`. Used when a
     * toast asks a question rather than just offering a shortcut, so both
     * answers are visible instead of one being implied by inaction.
     */
    secondaryAction?: ToastAction;
};

/**
 * A sidebar entry. Both `permission` and `feature` are optional gates:
 * permission hides what the user may not do, feature hides what the plan does
 * not include (§108.1).
 */
export type NavItem = {
    label: string;
    href?: string;
    icon: string;
    permission?: string;
    feature?: string;
    badge?: number | string;
    children?: NavItem[];
};

export type NavSection = {
    label?: string;
    items: NavItem[];
};

export type BreadcrumbItem = {
    label: string;
    href?: string;
};

/** Widget catalog entry, matching the §80 data contract. */
export type DashboardWidget = {
    key: string;
    name: string;
    type: string;
    category: string;
    permission: string | null;
    configurable: boolean;
    refreshable: boolean;
    date_filter: boolean;
};

export type WidgetLayout = {
    widget_key: string;
    x: number;
    y: number;
    width: number;
    height: number;
    config?: Record<string, unknown>;
};
