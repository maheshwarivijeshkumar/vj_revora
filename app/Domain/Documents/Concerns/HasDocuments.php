<?php

declare(strict_types=1);

namespace App\Domain\Documents\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Files attached to a record (§21).
 *
 * Separate from HasTimeline on purpose: a document is not something that
 * happened, it is something the record holds, and it outlives the activity
 * that produced it.
 */
trait HasDocuments
{
    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'subject');
    }
}
