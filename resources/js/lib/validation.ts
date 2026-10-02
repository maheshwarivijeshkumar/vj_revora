import { computed, reactive, watch, type ComputedRef } from 'vue';

/**
 * Lightweight client-side validation.
 *
 * This exists for speed of feedback only. Laravel remains authoritative
 * (§42, §101.16) and every rule here has a server counterpart; if the two ever
 * disagree, the server wins and its message replaces the local one.
 *
 * Deliberately not vee-validate: Inertia's `useForm` already owns the form
 * state and the server errors, so a second form library would mean two sources
 * of truth for the same fields.
 */

export type Rule = (value: unknown) => string | null;

const isBlank = (value: unknown): boolean =>
    value === null ||
    value === undefined ||
    (typeof value === 'string' && value.trim() === '') ||
    (Array.isArray(value) && value.length === 0);

export const required =
    (label: string): Rule =>
    (value) =>
        isBlank(value) ? `${label} is required.` : null;

/**
 * Matches Laravel's `email:rfc` closely enough to catch typos, without
 * pretending to implement RFC 5322. Anything this accepts and the server
 * rejects still surfaces as a server error.
 */
export const email =
    (message = 'Please enter a valid email address.'): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        const text = String(value).trim();
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(text) ? null : message;
    };

export const minLength =
    (length: number, message: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return String(value).trim().length < length ? message : null;
    };

export const maxLength =
    (length: number, label: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return String(value).length > length
            ? `${label} must be ${length} characters or fewer.`
            : null;
    };

export const accepted =
    (message: string): Rule =>
    (value) =>
        value === true ? null : message;

export const oneOf =
    (values: readonly string[], message: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return values.includes(String(value)) ? null : message;
    };

/**
 * The shape this composable needs from a form. Inertia's `useForm` satisfies
 * it, but nothing here depends on Inertia specifically.
 */
type ValidatableForm = {
    errors: Record<string, string | undefined>;
};

/** Only the string-keyed data fields are validatable. */
type Field<TForm> = Extract<keyof TForm, string>;

/**
 * Validation state for an Inertia form.
 *
 * Fields validate on blur and on submit, never on every keystroke: telling
 * someone their email is invalid while they are still typing the first
 * character is noise, not help.
 *
 * @param form   An Inertia `useForm` instance.
 * @param rules  Rules per field.
 */
export function useFormValidation<TForm extends ValidatableForm>(
    form: TForm,
    // Keyed off the form's own fields, so a typo in a rule name is a type
    // error rather than a rule that silently never runs.
    rules: Partial<Record<Field<TForm>, Rule[]>>,
) {
    const touched = reactive<Record<string, boolean>>({});
    const local = reactive<Record<string, string | null>>({});

    /**
     * Fields edited since the last server response.
     *
     * A server error describes the value that was submitted, so it is stale
     * the moment the field changes. Suppressing it locally rather than calling
     * `form.clearErrors()` keeps this composable independent of Inertia's form
     * API, and avoids mutating state the form itself owns.
     */
    const edited = reactive<Record<string, boolean>>({});

    function check(field: Field<TForm>): string | null {
        const fieldRules = rules[field];

        if (!fieldRules) {
            return null;
        }

        for (const rule of fieldRules) {
            const message = rule(form[field]);
            if (message) {
                return message;
            }
        }

        return null;
    }

    /** Marks a field as touched and validates it. Call from `@blur`. */
    function touch(field: Field<TForm>): void {
        touched[field] = true;
        local[field] = check(field);
    }

    /**
     * Re-checks a field that already failed, so a correction clears the error
     * as soon as it is valid rather than only on the next blur.
     */
    function revalidate(field: Field<TForm>): void {
        if (touched[field] && local[field]) {
            local[field] = check(field);
        }
        edited[field] = true;
    }

    /** Validates everything and marks all fields touched. Returns validity. */
    function validate(): boolean {
        let valid = true;

        for (const field of Object.keys(rules) as Field<TForm>[]) {
            touched[field] = true;
            local[field] = check(field);
            if (local[field]) {
                valid = false;
            }
        }

        return valid;
    }

    function reset(): void {
        for (const key of Object.keys(touched)) {
            delete touched[key];
            delete local[key];
        }
        for (const key of Object.keys(edited)) {
            delete edited[key];
        }
    }

    // A fresh server response supersedes any local suppression: these errors
    // describe the values as just submitted.
    watch(
        () => form.errors,
        () => {
            for (const key of Object.keys(edited)) {
                delete edited[key];
            }
        },
    );

    /**
     * Server errors take precedence: they reflect the decision that actually
     * counts, and may catch things the client cannot know about.
     */
    const errors: ComputedRef<Record<string, string | undefined>> = computed(
        () => {
            const merged: Record<string, string | undefined> = {};

            for (const field of Object.keys(rules)) {
                const server = edited[field] ? undefined : form.errors[field];

                merged[field] =
                    server ??
                    (touched[field] ? (local[field] ?? undefined) : undefined);
            }

            return { ...form.errors, ...merged };
        },
    );

    return { errors, touch, revalidate, validate, reset };
}
