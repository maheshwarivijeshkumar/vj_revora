import { computed, reactive, watch, type ComputedRef } from 'vue';

/**
 * Client-side validation.
 *
 * This exists for speed of feedback only. Laravel remains authoritative
 * (§42, §101.16) and every rule here has a server counterpart; if the two ever
 * disagree, the server wins and its message replaces the local one.
 *
 * Browser-native validation is switched off everywhere (`novalidate`, and the
 * inputs set `aria-required` rather than `required`). That is deliberate: the
 * native bubble cannot be styled, says different things in every browser,
 * disappears the moment you type, is unreadable to a screen reader, and stops
 * at the first field. None of that is acceptable for a form somebody fills in
 * forty times a day.
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

/**
 * A value as text, for the rules that inspect its shape.
 *
 * Narrowed rather than coerced: `String({})` is `"[object Object]"`, which
 * would pass a length check and fail an email check for reasons nobody could
 * work out from the message. Anything that is not already a string or a number
 * is not text, and the rules treat it as blank.
 */
const text = (value: unknown): string => {
    if (typeof value === 'string') {
        return value.trim();
    }

    return typeof value === 'number' && Number.isFinite(value)
        ? String(value)
        : '';
};

// --- Rules ------------------------------------------------------------------
//
// Every rule passes a blank value. "This is required" is one rule's job, so a
// blank field reports one message rather than four.

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
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(text(value))
            ? null
            : message;
    };

export const minLength =
    (length: number, message: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return text(value).length < length ? message : null;
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
 * A full absolute URL, which is what Laravel's `url` rule accepts.
 *
 * `URL` rather than a regex: it is the same parser the browser uses, so what
 * this accepts is what a link will actually open.
 */
export const url =
    (message = 'Enter a full address, including https://.'): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }

        try {
            const parsed = new URL(text(value));

            return parsed.protocol === 'http:' || parsed.protocol === 'https:'
                ? null
                : message;
        } catch {
            return message;
        }
    };

/** An ISO 3166-1 alpha-2 code, which is what the country columns store. */
export const countryCode =
    (message = 'Use the two-letter country code, such as AE or GB.'): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return /^[A-Za-z]{2}$/.test(text(value)) ? null : message;
    };

/**
 * A rough shape check for a phone number.
 *
 * Deliberately permissive: the server validates against Google's per-country
 * numbering plans, and this cannot, so anything stricter here would reject
 * numbers the server would have accepted. It catches a letter or a number with
 * obviously too few digits, and leaves the real answer to the round trip.
 */
export const phone =
    (
        message = 'Enter a phone number, with a country code such as +971.',
    ): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }

        const raw = text(value);
        const digits = raw.replace(/\D/g, '');

        if (/[A-Za-z]/.test(raw) || digits.length < 7 || digits.length > 15) {
            return message;
        }

        return null;
    };

export const numeric =
    (label: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return Number.isFinite(Number(value))
            ? null
            : `${label} must be a number.`;
    };

export const between =
    (min: number, max: number, label: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }

        const parsed = Number(value);

        if (!Number.isFinite(parsed)) {
            return `${label} must be a number.`;
        }

        return parsed < min || parsed > max
            ? `${label} must be between ${min} and ${max}.`
            : null;
    };

export const minimum =
    (min: number, label: string): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }

        const parsed = Number(value);

        if (!Number.isFinite(parsed)) {
            return `${label} must be a number.`;
        }

        return parsed < min ? `${label} cannot be less than ${min}.` : null;
    };

export const date =
    (message = 'Enter a valid date.'): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }
        return Number.isNaN(Date.parse(text(value))) ? message : null;
    };

/**
 * A date at or after today, in the viewer's own timezone.
 *
 * Compared on the date alone: "tomorrow" should not depend on what time of day
 * the form is being filled in.
 */
export const notInThePast =
    (message = 'Choose today or a later date.'): Rule =>
    (value) => {
        if (isBlank(value)) {
            return null;
        }

        const parsed = new Date(text(value));

        if (Number.isNaN(parsed.getTime())) {
            return 'Enter a valid date.';
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        parsed.setHours(0, 0, 0, 0);

        return parsed < today ? message : null;
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
 * A rule that depends on more than one field.
 *
 * "Give an email address or a phone number" belongs to the pair, not to either
 * field, so expressing it per-field would either report it twice or attach it
 * to whichever one happened to come first.
 */
export type FieldGroup<TForm> = {
    /** The fields it concerns, in the order they appear on screen. */
    fields: Field<TForm>[];
    /** True when the group is satisfied. */
    check: (form: TForm) => boolean;
    message: string;
};

export type ValidationOptions<TForm> = {
    /**
     * Human labels, used by the error summary. A summary that says
     * "lead_source_id" helps nobody.
     */
    labels?: Partial<Record<Field<TForm>, string>>;
    /**
     * DOM ids, when they differ from the field name — which they usually do,
     * because ids must be unique across a page and field names are not.
     */
    ids?: Partial<Record<Field<TForm>, string>>;
    groups?: FieldGroup<TForm>[];
};

/**
 * Validation state for an Inertia form.
 *
 * Fields validate on blur and on submit, never on every keystroke: telling
 * someone their email is invalid while they are still typing the first
 * character is noise, not help. Once a field has failed, it re-checks as they
 * type, so a correction clears the message immediately rather than on the next
 * blur.
 *
 * @param form   An Inertia `useForm` instance.
 * @param rules  Rules per field.
 */
export function useFormValidation<TForm extends ValidatableForm>(
    form: TForm,
    // Keyed off the form's own fields, so a typo in a rule name is a type
    // error rather than a rule that silently never runs.
    rules: Partial<Record<Field<TForm>, Rule[]>>,
    options: ValidationOptions<TForm> = {},
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

    /** Group messages, keyed by the first field in each group. */
    const groupErrors = reactive<Record<string, string | null>>({});

    const fields = Object.keys(rules) as Field<TForm>[];

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

    function checkGroups(): void {
        for (const group of options.groups ?? []) {
            const anchor = group.fields[0];
            groupErrors[anchor] = group.check(form) ? null : group.message;
        }
    }

    /** Marks a field as touched and validates it. Call from `@blur`. */
    function touch(field: Field<TForm>): void {
        touched[field] = true;
        local[field] = check(field);
        checkGroups();
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

        // Groups re-check on every edit: the field that fixes "email or phone"
        // is usually not the one showing the message.
        checkGroups();
    }

    /** Validates everything and marks all fields touched. Returns validity. */
    function validate(): boolean {
        let valid = true;

        for (const field of fields) {
            touched[field] = true;
            local[field] = check(field);
            if (local[field]) {
                valid = false;
            }
        }

        checkGroups();

        for (const group of options.groups ?? []) {
            if (groupErrors[group.fields[0]]) {
                valid = false;

                // Touched so the fields themselves show as invalid, not only
                // the summary.
                for (const field of group.fields) {
                    touched[field] = true;
                }
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
        for (const key of Object.keys(groupErrors)) {
            delete groupErrors[key];
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

            for (const field of fields) {
                const server = edited[field] ? undefined : form.errors[field];

                merged[field] =
                    server ??
                    (touched[field]
                        ? (local[field] ?? groupErrors[field] ?? undefined)
                        : undefined);
            }

            return { ...form.errors, ...merged };
        },
    );

    /**
     * Whether a field has been touched and has nothing wrong with it.
     *
     * Used for the affirmative tick: silence is ambiguous on a long form, and
     * "this one is fine" is worth saying once somebody has left the field.
     */
    function isSettled(field: Field<TForm>): boolean {
        return Boolean(touched[field]) && !errors.value[field];
    }

    function labelFor(field: string): string {
        return (
            options.labels?.[field as Field<TForm>] ??
            field
                .replace(/_id$/, '')
                .replace(/_/g, ' ')
                .replace(/^./, (c) => c.toUpperCase())
        );
    }

    /**
     * Everything currently wrong, in the order the fields appear on screen.
     *
     * Drives the summary at the top of the form. WCAG 3.3.1 asks for errors to
     * be identified in text; a summary also means somebody who submitted a long
     * form does not have to hunt for which of twenty fields failed.
     */
    const summary = computed(() => {
        // Every ruled field counts as handled whether or not it has a message.
        // Otherwise the pass below would re-add a stale server error that the
        // field itself has already suppressed, and the summary would contradict
        // the field it points at.
        const seen = new Set<string>(fields);
        const result: {
            field: string;
            id: string;
            label: string;
            message: string;
        }[] = [];

        for (const field of fields) {
            const message = errors.value[field];

            if (!message) {
                continue;
            }

            result.push({
                field,
                id: options.ids?.[field] ?? field,
                label: labelFor(field),
                message,
            });
        }

        // Server errors for fields with no client rules still belong here.
        for (const [field, message] of Object.entries(form.errors)) {
            if (!message || seen.has(field)) {
                continue;
            }

            seen.add(field);
            result.push({
                field,
                id: options.ids?.[field as Field<TForm>] ?? field,
                label: labelFor(field),
                message,
            });
        }

        return result;
    });

    const hasErrors = computed(() => summary.value.length > 0);

    /**
     * Moves focus to the first thing that needs attention.
     *
     * In a scrolling drawer the failing field is often off-screen, so a submit
     * that silently does nothing is indistinguishable from a broken button.
     */
    function focusFirstError(): boolean {
        const first = summary.value[0];

        if (!first) {
            return false;
        }

        const element = document.getElementById(first.id);

        if (!element) {
            return false;
        }

        element.focus({ preventScroll: true });
        element.scrollIntoView({ block: 'center', behavior: 'smooth' });

        return true;
    }

    /**
     * Validates, and on failure puts the cursor where the problem is.
     *
     * The submit button is never disabled: a disabled button explains nothing,
     * and the one thing somebody will do when a form looks stuck is press it
     * again (§59).
     */
    function validateAndFocus(): boolean {
        if (validate()) {
            return true;
        }

        focusFirstError();

        return false;
    }

    return {
        errors,
        summary,
        hasErrors,
        touch,
        revalidate,
        validate,
        validateAndFocus,
        focusFirstError,
        isSettled,
        reset,
    };
}
