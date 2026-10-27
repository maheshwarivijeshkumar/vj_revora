<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the human timeline (§21).
 *
 * Notes and tasks record themselves here as a side effect of being created, so
 * the timeline is one list rather than three the reader has to merge in their
 * head. Calls, meetings, emails and messages are logged directly.
 */
final class ActivityRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Model $subject,
        ActivityType $type,
        string $summary,
        ?string $body = null,
        ?\DateTimeInterface $occurredAt = null,
        ?int $durationMinutes = null,
        array $metadata = [],
    ): Activity {
        return Activity::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'type' => $type,
            'summary' => $summary,
            'body' => $body,
            'user_id' => Auth::id(),
            // Defaults to now, but a caller can backdate: a call logged on
            // Monday for a conversation on Friday belongs on Friday.
            'occurred_at' => $occurredAt ?? now(),
            'duration_minutes' => $durationMinutes,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
