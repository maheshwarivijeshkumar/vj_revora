/**
 * Copies text to the clipboard, including where the modern API is unavailable.
 *
 * `navigator.clipboard` only exists in a secure context, so on a plain-HTTP
 * development host like `http://myapp.test` it is `undefined` — and the copy
 * button for an API key, a webhook signing secret or an error reference is
 * exactly the thing somebody needs to work, because the alternative is reading
 * a 48-character secret off the screen by eye.
 *
 * `document.execCommand('copy')` is deprecated and still the only fallback that
 * works there. It is tried second, and only its own failure is reported as a
 * failure — so the caller can tell the user to select and copy by hand rather
 * than silently doing nothing.
 */
export async function copyText(value: string): Promise<boolean> {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(value);

            return true;
        } catch {
            // Permission refused, or the document is not focused. Fall through:
            // the legacy path often still works.
        }
    }

    return copyViaTextarea(value);
}

/**
 * The pre-Clipboard-API approach: put the text in a field, select it, copy.
 *
 * Positioned off-screen rather than hidden, because `display: none` and
 * `visibility: hidden` elements cannot be selected and the copy silently does
 * nothing.
 */
function copyViaTextarea(value: string): boolean {
    const field = document.createElement('textarea');

    field.value = value;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '-1000px';
    field.style.opacity = '0';
    // So a screen reader does not announce a stray field appearing and leaving.
    field.setAttribute('aria-hidden', 'true');
    field.tabIndex = -1;

    document.body.appendChild(field);

    // Remembered so focus returns to whatever the user was on, rather than
    // being dropped to the document.
    const previous = document.activeElement as HTMLElement | null;

    try {
        field.select();
        field.setSelectionRange(0, value.length);

        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(field);
        previous?.focus();
    }
}
