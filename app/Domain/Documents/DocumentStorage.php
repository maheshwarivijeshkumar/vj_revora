<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Tenancy\TenantContext;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Writes an upload to disk and records it (§21, §55).
 *
 * One place that touches the filesystem, so the rules about where files go and
 * what they are called cannot be half-applied by a second caller.
 */
final readonly class DocumentStorage
{
    public function __construct(private TenantContext $context) {}

    /**
     * Stores a file against a record.
     *
     * The same file uploaded twice to the same record returns the existing row
     * rather than a second copy: a duplicate attachment is almost always a
     * double-clicked button, and quietly stacking them makes the panel useless.
     *
     * @param  array{title?: string|null, description?: string|null}  $details
     */
    public function store(
        Model $subject,
        UploadedFile $file,
        ?int $uploadedBy = null,
        array $details = [],
    ): Document {
        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        $existing = Document::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('checksum', $checksum)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        $disk = $this->disk();
        // The extension comes from the guesser, not from the submitted name:
        // nothing the uploader typed reaches the filesystem, so "report.pdf.php"
        // and "../../.env" are not something the path has to survive.
        $extension = Str::lower($file->extension() ?: $file->getClientOriginalExtension());

        $path = $file->storeAs(
            $this->directory(),
            $extension === '' ? $uuid : "{$uuid}.{$extension}",
            ['disk' => $disk],
        );

        if ($path === false) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        try {
            return Document::create([
                'uuid' => $uuid,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'disk' => $disk,
                'path' => $path,
                // Kept so the download gives back the name the person recognises,
                // sanitised because it ends up in a response header.
                'original_name' => $this->safeName($file->getClientOriginalName()),
                'extension' => $extension,
                // The detected type, never the client-supplied Content-Type, which
                // is just another field the uploader controls.
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'checksum' => $checksum,
                'title' => $details['title'] ?? null,
                'description' => $details['description'] ?? null,
                'uploaded_by' => $uploadedBy,
            ]);
        } catch (Throwable $e) {
            // The bytes are already on disk. Leaving them there would orphan a
            // file no row points at, which nothing can then find or purge.
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    /**
     * Removes the row, keeping the file.
     *
     * A soft delete that destroyed the bytes would make "restore" a lie.
     */
    public function softDelete(Document $document): void
    {
        $document->delete();
    }

    /**
     * Removes the row and the bytes, permanently.
     */
    public function purge(Document $document): void
    {
        Storage::disk($document->disk)->delete($document->path);

        $document->forceDelete();
    }

    /**
     * Per-tenant directory.
     *
     * So a path that leaks cannot be edited into another workspace's file, and
     * a tenant's storage can be measured or removed wholesale.
     */
    private function directory(): string
    {
        return 'documents/'.$this->context->tenantOrFail()->id;
    }

    private function disk(): string
    {
        /** @var string $disk */
        $disk = config('documents.disk', 'local');

        return $disk;
    }

    /**
     * Strips anything that would make the name dangerous in a header or a path.
     */
    private function safeName(string $name): string
    {
        return Str::limit(
            preg_replace('/[^\w\s.\-()\[\]]/u', '', basename($name)) ?: 'file',
            200,
            '',
        );
    }
}
