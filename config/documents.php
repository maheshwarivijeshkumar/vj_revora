<?php

declare(strict_types=1);

/**
 * File attachments against CRM records (§21, §55).
 *
 * The allowlists here are the whole security boundary for uploads, so they are
 * configuration rather than scattered validation rules: one place to audit, and
 * one place to change when a tenant genuinely needs another format.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | Deliberately a private disk. Uploads are served by a controller that
    | checks the session, the tenant and the permission; nothing lands under
    | the web root, so a leaked path is not a leaked file. Point this at `s3`
    | in production — the controller streams, so nothing changes.
    |
    */

    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Size limit
    |--------------------------------------------------------------------------
    |
    | Kilobytes, matching Laravel's `max:` rule. 20 MB covers signed contracts
    | and scanned IDs without letting one upload fill a tenant's quota.
    |
    */

    'max_size_kb' => (int) env('DOCUMENTS_MAX_SIZE_KB', 20480),

    /*
    |--------------------------------------------------------------------------
    | Permitted types
    |--------------------------------------------------------------------------
    |
    | An allowlist, never a blocklist: a blocklist is a bet that nobody will
    | find an extension you did not think of. Both lists have to pass —
    | `mimetypes` is checked against the file's actual contents, so renaming
    | a script to .pdf fails, and the extension list stops a real PDF being
    | stored under a name the web server might execute.
    |
    | SVG is absent on purpose. It is a script-carrying document, not an
    | image, and serving one from the application's own origin is stored XSS.
    |
    */

    'extensions' => [
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'rtf',
        'png', 'jpg', 'jpeg', 'gif', 'webp',
        'zip',
    ],

    'mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/rtf',
        'text/csv',
        'text/plain',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'application/zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inline preview
    |--------------------------------------------------------------------------
    |
    | The only types ever sent with `Content-Disposition: inline`. Everything
    | else downloads as an attachment, because a browser rendering an uploaded
    | file on this origin is how one tenant's upload reads another tenant's
    | session.
    |
    */

    'inline_types' => [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ],

];
