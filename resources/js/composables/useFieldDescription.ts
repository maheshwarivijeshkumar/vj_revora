import { computed, inject, type ComputedRef } from 'vue';

/**
 * The hint and error a FormField is showing, so the control inside it can
 * point at those messages wherever they sit in the layout.
 */
export type FieldDescription = {
    hintId?: string;
    errorId?: string;
};

export function useFieldDescription(props: {
    id: string;
    hint?: boolean;
    invalid?: boolean;
}): ComputedRef<string | undefined> {
    const field = inject<ComputedRef<FieldDescription> | null>('formField', null);

    return computed(() => {
        const ids = [
            props.hint ? `${props.id}-hint` : field?.value.hintId,
            props.invalid ? `${props.id}-error` : field?.value.errorId,
        ];

        const unique = ids.filter((id, index) => id && ids.indexOf(id) === index);

        return unique.length > 0 ? unique.join(' ') : undefined;
    });
}
