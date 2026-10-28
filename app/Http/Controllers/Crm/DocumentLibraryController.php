<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Files already attached anywhere in the workspace, in one list.
 */
final class DocumentLibraryController extends Controller
{
    public function __invoke(): Response
    {
        $documents = Document::query()
            ->with(['subject', 'uploader'])
            ->displayOrder()
            ->limit(100)
            ->get()
            ->map(function (Document $document): array {
                $subject = $document->subject;
                $name = null;

                if ($subject !== null) {
                    $name = match (true) {
                        method_exists($subject, 'displayName') => $subject->displayName(),
                        isset($subject->name) => (string) $subject->name,
                        isset($subject->title) => (string) $subject->title,
                        default => class_basename($subject),
                    };
                }

                return [
                    'id' => $document->id,
                    'title' => $document->title ?: $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'subject' => $name,
                    'uploader' => $document->uploader?->name,
                    'created_at' => $document->created_at?->toIso8601String(),
                    'href' => '/documents/'.$document->uuid,
                ];
            })
            ->all();

        return Inertia::render('crm/documents/Index', [
            'documents' => $documents,
        ]);
    }
}
