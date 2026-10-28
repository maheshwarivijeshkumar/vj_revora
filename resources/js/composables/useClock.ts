import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Dates and times in the signed-in person's zone.
 *
 * The zone label is the Windows form, such as (UTC+05:00) Islamabad, Karachi.
 */
export function useClock() {
    const page = usePage();

    const timeZone = computed(
        () => page.props.auth?.user?.timezone ?? 'UTC',
    );

    const timeZoneLabel = computed(
        () => page.props.auth?.user?.timezone_label ?? timeZone.value,
    );

    function formatDateTime(
        value: string | null,
        options: Intl.DateTimeFormatOptions = {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        },
    ): string {
        if (!value) {
            return '';
        }

        return new Intl.DateTimeFormat(undefined, {
            ...options,
            timeZone: timeZone.value,
        }).format(new Date(value));
    }

    function formatDate(
        value: string | null,
        options: Intl.DateTimeFormatOptions = {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        },
    ): string {
        return formatDateTime(value, options);
    }

    return { timeZone, timeZoneLabel, formatDateTime, formatDate };
}
