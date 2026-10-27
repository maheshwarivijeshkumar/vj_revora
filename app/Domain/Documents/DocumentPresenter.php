<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;

/**
 * Shapes a record's attachments for the UI (§21, §44).
 *
 * One presenter for every entity, so no screen invents its own idea of what a
 * file's size or name is.
 */
final class DocumentPresenter
{
    /** Enough to hold a long-running account without paging. */
    private const LIMIT = 100;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forSubject(Model $subject): array
    {
        return Document::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->with('uploader:id,name')
            ->displayOrder()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Document $document): array => [
                'uuid' => $document->uuid,
                'name' => $document->displayName(),
                'original_name' => $document->original_name,
                'description' => $document->description,
                'extension' => $document->extension,
                'mime_type' => $document->mime_type,
                'size' => $document->sizeForHumans(),
                // Whether the UI may offer "open" as well as "download".
                'can_preview' => $document->isInlineSafe(),
                'uploaded_by' => $document->uploader?->name,
                'created_at' => $document->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * What the uploader has to obey, so the client can say so before a file is
     * sent rather than after a failed round trip (§59).
     *
     * @return array<string, mixed>
     */
    public function constraints(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('documents.extensions', []);

        return [
            'extensions' => $extensions,
            'max_size_kb' => (int) config('documents.max_size_kb', 20480),
            // The attribute an <input type="file"> wants.
            'accept' => implode(',', array_map(
                fn (string $extension): string => '.'.$extension,
                $extensions,
            )),
        ];
    }
}
