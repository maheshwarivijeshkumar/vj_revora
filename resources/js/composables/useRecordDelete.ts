import { router } from '@inertiajs/vue3';
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';

/**
 * Holds a delete until the person accepts the confirmation.
 *
 * Opening the dialog does not call the server. Cancel leaves every record
 * where it was.
 */
export function useRecordDelete(
    resource: MaybeRefOrGetter<string>,
    noun: MaybeRefOrGetter<string>,
    plural: MaybeRefOrGetter<string>,
) {
    const selected = ref<(number | string)[]>([]);
    const pending = ref<(number | string)[]>([]);
    const busy = ref(false);

    const open = computed(() => pending.value.length > 0);

    const title = computed(() => {
        const count = pending.value.length;

        if (count === 1) {
            return `Delete this ${toValue(noun)}?`;
        }

        return `Delete these ${count} ${toValue(plural)}?`;
    });

    const message = computed(() => {
        const count = pending.value.length;

        if (count === 1) {
            return `Are you sure you want to delete this ${toValue(noun)}?`;
        }

        return `Are you sure you want to delete these ${count} ${toValue(plural)}?`;
    });

    function ask(ids: (number | string)[]): void {
        if (ids.length === 0 || busy.value) {
            return;
        }

        pending.value = [...ids];
    }

    function cancel(): void {
        if (busy.value) {
            return;
        }

        pending.value = [];
    }

    function confirm(): void {
        const name = toValue(resource);

        if (pending.value.length === 0 || busy.value || name === '') {
            return;
        }

        busy.value = true;

        router.post(
            '/operations/bulk-delete',
            {
                resource: name,
                ids: pending.value.map((id) => Number(id)),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    busy.value = false;
                    pending.value = [];
                    selected.value = [];
                },
            },
        );
    }

    return { selected, open, title, message, busy, ask, cancel, confirm };
}
