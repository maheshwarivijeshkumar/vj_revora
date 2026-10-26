<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Messaging\Exceptions\CannotMessageLead;
use App\Domain\Messaging\TemplateRenderer;
use App\Models\EmailAccount;
use App\Models\FollowUpEnrollment;
use App\Models\FollowUpStep;
use Illuminate\Support\Carbon;

/**
 * Moves one enrollment forward: sends the step that is due, or, once every
 * step has gone out and the reply window has closed, scores the lead 0/10.
 *
 * Step times are measured from enrollment, not from the previous email, so
 * "6 hours" means 6 hours after the first email even if a send was retried.
 */
final readonly class AdvanceFollowUp
{
    /** How long to wait before retrying a send that failed. */
    public const RETRY_MINUTES = 30;

    public function __construct(
        private SendEmailToLead $send,
        private TemplateRenderer $renderer,
    ) {}

    public function handle(FollowUpEnrollment $enrollment): void
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            return;
        }

        $lead = $enrollment->lead;
        $sequence = $enrollment->sequence()->with('steps.template')->first();

        if ($lead === null || $sequence === null) {
            $this->finish($enrollment, EnrollmentStatus::Stopped, 'The lead or sequence was removed.');

            return;
        }

        if ($lead->email_opted_out_at !== null) {
            $this->finish($enrollment, EnrollmentStatus::Stopped, 'The lead unsubscribed.');

            return;
        }

        if (! $sequence->is_active) {
            $this->finish($enrollment, EnrollmentStatus::Stopped, 'The follow-up sequence was turned off.');

            return;
        }

        /** @var FollowUpStep|null $step */
        $step = $sequence->steps->firstWhere('position', $enrollment->next_position);

        if ($step === null) {
            $this->closeReplyWindow($enrollment, $sequence->no_reply_after_minutes, $sequence->steps->count());

            return;
        }

        $account = $enrollment->emailAccount ?? EmailAccount::sender();

        if ($account === null || $step->template === null) {
            $this->retryLater($enrollment, $account === null
                ? CannotMessageLead::noSender('email')->getMessage()
                : 'Step '.$step->position.' has no template.');

            return;
        }

        try {
            $this->send->handle(
                $lead,
                $account,
                $this->renderer->render($step->template->subject, $lead, $account),
                $this->renderer->render($step->template->body, $lead, $account),
                ['enrollment_id' => $enrollment->id, 'step' => $step->position],
            );
        } catch (CannotMessageLead $e) {
            $this->finish($enrollment, EnrollmentStatus::Failed, $e->getMessage());

            return;
        } catch (ProviderAuthException|ProviderRequestException $e) {
            $this->retryLater($enrollment, $e->getMessage());

            return;
        }

        $next = $sequence->steps->firstWhere('position', $step->position + 1);
        $nextAt = $next === null
            ? $enrollment->started_at->copy()->addMinutes($sequence->no_reply_after_minutes)
            : $enrollment->started_at->copy()->addMinutes($next->delay_minutes);

        $enrollment->forceFill([
            'email_account_id' => $account->id,
            'next_position' => $step->position + 1,
            'next_run_at' => Carbon::instance($nextAt)->max(now()),
            'last_error' => null,
        ])->save();
    }

    private function closeReplyWindow(FollowUpEnrollment $enrollment, int $windowMinutes, int $stepCount): void
    {
        $deadline = $enrollment->started_at->copy()->addMinutes($windowMinutes);

        if ($deadline->isFuture()) {
            $enrollment->forceFill(['next_run_at' => $deadline])->save();

            return;
        }

        $this->finish($enrollment, EnrollmentStatus::NoReply, null);

        $enrollment->lead?->forceFill([
            'engagement_score' => 0,
            'engagement_reason' => "No reply to {$stepCount} follow-up emails",
            'engagement_scored_at' => now(),
        ])->save();
    }

    private function retryLater(FollowUpEnrollment $enrollment, string $error): void
    {
        $enrollment->forceFill([
            'next_run_at' => now()->addMinutes(self::RETRY_MINUTES),
            'last_error' => $error,
        ])->save();
    }

    private function finish(FollowUpEnrollment $enrollment, EnrollmentStatus $status, ?string $error): void
    {
        $enrollment->forceFill([
            'status' => $status,
            'next_run_at' => null,
            'finished_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
