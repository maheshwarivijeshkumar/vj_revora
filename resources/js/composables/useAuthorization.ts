import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { EntitlementMap } from '@/types/app';

/**
 * Permission and entitlement checks for the UI (§101.7, §93).
 *
 * These mirror the server's answer so the interface hides and disables
 * consistently with what a request would actually be allowed to do. They are
 * *not* the security boundary — policies and entitlement middleware are.
 * Hiding a button the server would reject is a UX improvement; showing one it
 * would accept is the bug this prevents.
 */
export function useAuthorization() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => (page.props.permissions as string[] | undefined) ?? [],
    );

    const entitlements = computed<EntitlementMap>(
        () => (page.props.entitlements as EntitlementMap | undefined) ?? {},
    );

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    function canAny(...keys: string[]): boolean {
        return keys.some((key) => can(key));
    }

    function hasFeature(key: string): boolean {
        return entitlements.value[key]?.enabled ?? false;
    }

    function remaining(key: string): number | null {
        return entitlements.value[key]?.remaining ?? null;
    }

    /** True once usage crosses the warning threshold for a metered feature. */
    function nearingLimit(key: string, threshold = 0.8): boolean {
        const entitlement = entitlements.value[key];
        if (!entitlement?.limit) {
            return false;
        }
        return entitlement.used / entitlement.limit >= threshold;
    }

    return {
        permissions,
        entitlements,
        can,
        canAny,
        hasFeature,
        remaining,
        nearingLimit,
    };
}
