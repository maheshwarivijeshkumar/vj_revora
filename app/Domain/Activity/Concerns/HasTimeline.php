<?php

declare(strict_types=1);

namespace App\Domain\Activity\Concerns;

use App\Models\Activity;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Activities, tasks and notes against a record (§21).
 *
 * One concern for all four entities rather than three relations written out
 * four times: the duplication is how one entity quietly ends up without notes,
 * and nobody notices until a customer asks.
 *
 * `tenant_id` is not pinned on these the way it is on the taggable and
 * company_contact pivots, because these are first-class models with the
 * BelongsToTenant trait — the global scope and the auto-fill both already
 * apply.
 */
trait HasTimeline
{
    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @return MorphMany<Task, $this> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject');
    }

    /** @return MorphMany<Note, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'subject');
    }
}
