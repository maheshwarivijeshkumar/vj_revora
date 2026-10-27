<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Models\EmailTemplate;
use App\Models\FollowUpSequence;
use Illuminate\Support\Facades\DB;

/**
 * The workspace's follow-up sequence, created with sensible defaults the
 * first time it is needed: an email at once, another at 6 hours, a last one
 * at 24 hours, and "no reply" scored 0/10 at 72 hours.
 */
final class EnsureDefaultFollowUp
{
    public const NO_REPLY_AFTER_MINUTES = 72 * 60;

    /** @var list<array{delay: int, name: string, subject: string, body: string}> */
    private const STEPS = [
        [
            'delay' => 0,
            'name' => 'Follow-up 1: thank you',
            'subject' => 'Thanks for your interest, {{first_name}}',
            'body' => "Hi {{first_name}},\n\nThank you for getting in touch. I'd love to understand what you're looking for and how we can help.\n\nWould you have 15 minutes for a quick call this week? Just reply with a time that suits you.\n\nBest regards,\n{{sender_name}}",
        ],
        [
            'delay' => 6 * 60,
            'name' => 'Follow-up 2: quick check-in',
            'subject' => 'Re: Thanks for your interest, {{first_name}}',
            'body' => "Hi {{first_name}},\n\nJust following up on my earlier email. Is there anything I can answer for you, or a good time for a short call?\n\nBest regards,\n{{sender_name}}",
        ],
        [
            'delay' => 24 * 60,
            'name' => 'Follow-up 3: last note',
            'subject' => 'Re: Thanks for your interest, {{first_name}}',
            'body' => "Hi {{first_name}},\n\nI don't want to fill your inbox, so this is my last note for now. If you're still interested, simply reply to this email and I'll get back to you right away.\n\nBest regards,\n{{sender_name}}",
        ],
    ];

    public function handle(): FollowUpSequence
    {
        $existing = FollowUpSequence::query()->with('steps')->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function (): FollowUpSequence {
            $sequence = FollowUpSequence::create([
                'name' => 'New lead follow-up',
                'is_active' => true,
                'no_reply_after_minutes' => self::NO_REPLY_AFTER_MINUTES,
            ]);

            foreach (self::STEPS as $i => $step) {
                $template = EmailTemplate::create([
                    'name' => $step['name'],
                    'subject' => $step['subject'],
                    'body' => $step['body'],
                ]);

                $sequence->steps()->create([
                    'position' => $i + 1,
                    'delay_minutes' => $step['delay'],
                    'email_template_id' => $template->id,
                ]);
            }

            return $sequence->load('steps');
        });
    }
}
