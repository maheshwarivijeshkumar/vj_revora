<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Domain\Ai\Writer;
use App\Domain\Opportunities\Enums\OpportunityStatus;
use App\Models\JobPost;
use App\Models\OpportunityMatch;
use App\Models\ServiceProfile;
use App\Models\Tender;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Drafts what to send for an opportunity, in the matched profile's voice.
 *
 *   Upwork job        a cover letter to paste into Upwork's proposal form
 *   job-board post    a short pitch to the company: hire us, not a full-timer
 *   government tender an expression-of-interest letter, plus a bid checklist
 *
 * The draft is never sent from here. Upwork forbids automated proposals, and
 * a tender is bid on EPADS; a person reads, edits and submits it (§89).
 */
final readonly class DraftProposal
{
    private const RULES = <<<'TEXT'
        You write proposals for a services business, in its voice.
        Use only facts given in the PROFILE. Never invent clients, numbers, certifications, registrations, team size, prices or timelines that are not in it.
        If the opportunity asks for something the profile does not show, say how it would be approached instead of claiming experience.
        Lead with the client's problem in the first sentence. No "Dear Hiring Manager", no "I hope this finds you well", no buzzwords, no emojis, no markdown headings.
        Write plain text with short paragraphs, ready to paste. Do not leave placeholders such as [Name].
        TEXT;

    public function __construct(
        private Writer $writer,
    ) {}

    public function handle(OpportunityMatch $match): OpportunityMatch
    {
        $opportunity = $match->opportunity();
        $profile = $match->profile ?? ServiceProfile::default();

        if ($opportunity === null || $profile === null) {
            throw new RuntimeException('This opportunity or its service profile no longer exists.');
        }

        [$draft, $by] = $this->write($profile, $opportunity);

        $match->forceFill([
            'draft' => $draft,
            'drafted_by' => $by,
            'drafted_at' => now(),
            'status' => $match->status->isOpen() ? OpportunityStatus::Drafted : $match->status,
        ])->save();

        return $match;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function write(ServiceProfile $profile, Tender|JobPost $opportunity): array
    {
        $generator = $this->writer->generator();

        if ($generator !== null) {
            try {
                return [
                    $generator->generate(
                        self::RULES."\n\n".$this->task($profile, $opportunity)."\n\nPROFILE\n".$this->profileFacts($profile),
                        $this->opportunityFacts($opportunity),
                    ),
                    $generator->name(),
                ];
            } catch (RuntimeException $e) {
                Log::warning('AI drafting failed; used the template draft instead.', ['error' => $e->getMessage()]);
            }
        }

        return [$this->template($profile, $opportunity), 'template'];
    }

    private function task(ServiceProfile $profile, Tender|JobPost $opportunity): string
    {
        $voice = $profile->isAgency()
            ? 'Write as the agency, using "we".'
            : 'Write as the freelancer in the first person, using "I".';

        if ($opportunity instanceof Tender) {
            return $voice.' TASK: a formal expression-of-interest letter to the procuring organisation for this Government of Pakistan tender, 200-300 words. Quote the tender number and title, say which of the profile\'s services and past work match the scope, and say the bidding documents will be obtained through EPADS. After the letter, add a line "Before bidding:" with a short checklist of what the tender likely requires (e.g. FBR active-taxpayer status, NTN/STN, registration, past-experience evidence, bid security) phrased as things to confirm, not as claims.';
        }

        if ($opportunity->source === 'upwork') {
            return $voice.' TASK: an Upwork proposal cover letter, 150-220 words. First line restates what the client needs. Then two or three specific, relevant proof points from the profile\'s portfolio or experience, a brief approach in two or three steps, and one or two sharp questions about the job. End with a simple invitation to talk. No price unless the profile states one.';
        }

        return $voice.' TASK: a short outreach email to this company, 120-180 words, starting with a line "Subject: ...". They are hiring for this role; offer to deliver the same work as an outside team or contractor instead, faster to start and without a long hire. Mention the most relevant proof point from the profile and end with a low-effort next step (a 15-minute call).';
    }

    private function profileFacts(ServiceProfile $profile): string
    {
        $lines = [
            'Name: '.$profile->name.' ('.($profile->isAgency() ? 'agency' : 'freelancer').')',
            'Tagline: '.$profile->tagline,
            'Website: '.$profile->website,
            'Location: '.$profile->location,
            'About: '.$profile->about,
            'Services: '.implode('; ', array_map(fn (array $s): string => $s['name'].': '.($s['summary'] ?? ''), $profile->services)),
            'Technologies: '.implode(', ', $profile->skills ?? []),
            'Portfolio: '.implode('; ', array_map(fn (array $p): string => $p['name'].': '.($p['summary'] ?? ''), $profile->portfolio ?? [])),
            'Markets: '.implode(', ', $profile->markets ?? []),
            'Pricing: '.($profile->pricing ?? 'not stated: do not mention prices'),
            'Sign off with: '.$profile->signature,
        ];

        return implode("\n", $lines);
    }

    private function opportunityFacts(Tender|JobPost $opportunity): string
    {
        if ($opportunity instanceof Tender) {
            return implode("\n", array_filter([
                'Government of Pakistan tender (PPRA)',
                'Tender number: '.$opportunity->reference,
                $opportunity->buyer_reference ? 'Buyer reference: '.$opportunity->buyer_reference : null,
                'Title: '.$opportunity->title,
                'Organisation: '.trim(($opportunity->department ?? '').', '.($opportunity->organization ?? ''), ', '),
                $opportunity->city ? 'City: '.$opportunity->city : null,
                'Category: '.$opportunity->procurement_category.' · '.$opportunity->tender_type,
                $opportunity->closing_at ? 'Closing: '.$opportunity->closing_at->setTimezone('Asia/Karachi')->format('j M Y, g:i A').' PKT' : null,
                $opportunity->description ? 'Details: '.$opportunity->description : null,
            ]));
        }

        $description = trim(strip_tags(html_entity_decode((string) $opportunity->description)));

        return implode("\n", array_filter([
            $opportunity->sourceLabel().' job post',
            'Title: '.$opportunity->title,
            $opportunity->company ? 'Company: '.$opportunity->company : null,
            $opportunity->budgetLabel() ? 'Budget: '.$opportunity->budgetLabel() : null,
            $opportunity->location ? 'Location: '.$opportunity->location : null,
            $opportunity->tags ? 'Skills: '.implode(', ', $opportunity->tags) : null,
            'Description: '.mb_substr($description, 0, 6000),
        ]));
    }

    /**
     * A plain draft from the profile, used when no AI provider is set up.
     * Deliberately generic: it is a starting point to edit, not to send.
     */
    private function template(ServiceProfile $profile, Tender|JobPost $opportunity): string
    {
        $we = $profile->isAgency() ? 'we' : 'I';
        $our = $profile->isAgency() ? 'our' : 'my';
        $service = $profile->services[0]['name'] ?? 'software development';
        $proof = $profile->portfolio[0] ?? null;
        $proofLine = $proof ? ucfirst($our).' recent work includes '.$proof['name'].': '.($proof['summary'] ?? '') : '';

        if ($opportunity instanceof Tender) {
            return "Subject: Expression of interest: {$opportunity->reference}, {$opportunity->title}\n\n"
                ."To the Procurement Committee,\n\n"
                ."{$profile->name} would like to express interest in tender {$opportunity->reference}, \"{$opportunity->title}\". "
                .ucfirst($we)." deliver {$service} and related services, and {$proofLine}\n\n"
                .ucfirst($we)." will obtain the bidding documents through EPADS and submit a complete bid before the closing date.\n\n"
                ."{$profile->signature}\n\n"
                .'Before bidding: confirm FBR active-taxpayer status, NTN/STN, PPRA/EPADS registration, required past-experience evidence and bid security.';
        }

        $opening = $opportunity->source === 'upwork'
            ? "You need help with {$opportunity->title}, and this is the kind of work {$we} do every day."
            : "Subject: {$opportunity->title}: an experienced team that can start this week\n\nI saw you are hiring for {$opportunity->title}. Instead of a long hire, {$we} can deliver the same work as an outside team.";

        return "{$opening}\n\n{$proofLine}\n\n"
            .'Could you share a little more about the timeline and what success looks like for you? '
            ."Happy to suggest an approach on a short call.\n\n{$profile->signature}";
    }
}
