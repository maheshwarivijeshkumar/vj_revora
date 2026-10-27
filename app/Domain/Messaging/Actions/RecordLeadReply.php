<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Scoring\ScoreReply;
use App\Models\FollowUpEnrollment;
use App\Models\Lead;
use App\Models\Message;

/**
 * What happens when a lead answers, on email or WhatsApp.
 *
 * The follow-up sequence stops (nobody wants "just following up" after they
 * already replied), an opt-out request is honoured on that channel, and the
 * reply is scored out of 10.
 */
final readonly class RecordLeadReply
{
    /** Phrases that mean "stop contacting me" (§88). */
    private const OPT_OUT = [
        'unsubscribe', 'remove me', 'stop emailing', 'stop messaging', 'do not contact', "don't contact",
    ];

    public function __construct(
        private ScoreReply $scorer,
    ) {}

    public function handle(Lead $lead, Message $reply): void
    {
        $text = trim((string) $reply->body);

        FollowUpEnrollment::query()
            ->where('lead_id', $lead->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->get()
            ->each(fn (FollowUpEnrollment $enrollment) => $enrollment->forceFill([
                'status' => EnrollmentStatus::Replied,
                'next_run_at' => null,
                'finished_at' => now(),
            ])->save());

        $attributes = ['last_activity_at' => now()];

        if ($this->asksToStop($text, $reply->channel)) {
            $attributes[$reply->channel === MessageChannel::Email ? 'email_opted_out_at' : 'whatsapp_opted_out_at'] = now();
        }

        if ($text !== '') {
            $score = $this->scorer->handle($text, $reply->subject);

            $attributes += [
                'engagement_score' => $score->score,
                'engagement_reason' => $score->reason,
                'engagement_scored_at' => now(),
            ];
        }

        $lead->forceFill($attributes)->save();
    }

    private function asksToStop(string $text, MessageChannel $channel): bool
    {
        $lower = mb_strtolower($text);

        // WhatsApp's convention is a bare STOP.
        if ($channel === MessageChannel::WhatsApp && in_array(trim($lower, " .!\n"), ['stop', 'unsubscribe'], true)) {
            return true;
        }

        foreach (self::OPT_OUT as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
