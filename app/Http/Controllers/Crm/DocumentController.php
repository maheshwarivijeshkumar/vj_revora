<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Crm\Enums\SubjectType;
use App\Domain\Documents\DocumentStorage;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files attached to any CRM record (§21, §55).
 *
 * Uploads land on a private disk and come back out through `download()`, which
 * runs the session, tenant and permission checks first. Nothing is served by
 * URL from the web root, so a path that leaks is not a file that leaks.
 */
final class DocumentController extends Controller
{
    public function store(Request $request, DocumentStorage $storage): RedirectResponse
    {
        /** @var list<string> $extensions */
        $extensions = config('documents.extensions', []);
        /** @var list<string> $mimeTypes */
        $mimeTypes = config('documents.mime_types', []);

        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(SubjectType::values())],
            'subject_id' => ['required', 'integer'],
            'file' => [
                'required',
                'file',
                'max:'.(int) config('documents.max_size_kb', 20480),
                // Both: `mimetypes` reads the file's actual contents, so a
                // script renamed to .pdf fails it, and the extension list
                // stops a genuine PDF being stored under a name a web server
                // might decide to execute.
                'mimetypes:'.implode(',', $mimeTypes),
                'extensions:'.implode(',', $extensions),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'file.mimetypes' => 'That file type is not accepted. Allowed: '.implode(', ', $extensions).'.',
            'file.extensions' => 'That file type is not accepted. Allowed: '.implode(', ', $extensions).'.',
        ]);

        $subject = SubjectType::from($validated['subject_type'])->resolve($validated['subject_id']);

        $document = $storage->store(
            $subject,
            $request->file('file'),
            $request->user()?->id,
            [
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
            ],
        );

        // The timeline is the record's one history, so an attachment shows up
        // there rather than only in a tab somebody has to think to open.
        app(ActivityRecorder::class)->record(
            $subject,
            ActivityType::Document,
            "File attached: {$document->displayName()}",
        );

        return back()->with('success', 'File attached.');
    }

    /**
     * Streams a stored file back.
     *
     * Route-model binding resolves by uuid through the tenant scope, so a uuid
     * from another workspace is a 404 and there is no ownership check here to
     * forget (ADR-009).
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        abort_if(! $disk->exists($document->path), 404, 'That file is no longer stored.');

        // Inline only for the few types on the allowlist, and only when asked.
        // A browser rendering an arbitrary upload on this origin runs whatever
        // is in it against the session that opened it.
        $inline = $request->boolean('inline') && $document->isInlineSafe();

        // `response()` builds Content-Disposition itself, which is what
        // encodes a filename containing non-ASCII characters correctly.
        return $disk->response(
            $document->path,
            $document->original_name,
            [
                // The type recorded at upload from the file's own contents,
                // not whatever the disk guesses from the extension now.
                'Content-Type' => $document->mime_type,
                // Even on the inline allowlist, the browser must not
                // second-guess the type we declared.
                'X-Content-Type-Options' => 'nosniff',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    public function destroy(Document $document): RedirectResponse
    {
        // Soft deleted, and the bytes stay: a restore that cannot return the
        // file is not a restore. A scheduled purge removes both later.
        $document->delete();

        return back()->with('success', 'File removed.');
    }
}
