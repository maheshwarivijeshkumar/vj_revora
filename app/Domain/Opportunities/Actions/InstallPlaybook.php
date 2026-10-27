<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Domain\Messaging\Actions\EnsureDefaultFollowUp;
use App\Models\EmailTemplate;
use App\Models\FollowUpStep;
use App\Models\ServiceProfile;
use Illuminate\Support\Facades\DB;

/**
 * Installs the sales playbook: one email template per stage of the workflow,
 * for each service profile, and points the automatic follow-ups at the default
 * profile's first three.
 *
 *   1 First reply → 2 Follow-up (6h) → 3 Last follow-up (24h)    automatic
 *   4 Book a call → 5 Proposal sent → 6 Proposal follow-up         by hand
 *   7 Check-in (nurture) → 8 Welcome (won) / 9 Close the loop (lost)
 *   10 Tender expression of interest, 11 Pitch to a hiring company
 *
 * Safe to run again: existing templates are left as edited, and the sequence
 * is only re-pointed while it still uses the built-in generic emails.
 */
final readonly class InstallPlaybook
{
    public function __construct(
        private EnsureServiceProfiles $profiles,
        private EnsureDefaultFollowUp $followUp,
    ) {}

    /**
     * @return int templates created
     */
    public function handle(): int
    {
        $created = 0;
        $profiles = $this->profiles->handle();

        DB::transaction(function () use ($profiles, &$created): void {
            foreach ($profiles as $profile) {
                foreach ($this->templates($profile) as [$name, $subject, $body]) {
                    $template = EmailTemplate::query()->firstOrCreate(
                        ['name' => $this->prefix($profile).$name],
                        ['subject' => $subject, 'body' => $body],
                    );

                    $created += $template->wasRecentlyCreated ? 1 : 0;
                }
            }

            $this->pointSequenceAt($profiles->firstWhere('is_default', true) ?? $profiles->first());
        });

        return $created;
    }

    private function pointSequenceAt(?ServiceProfile $profile): void
    {
        if ($profile === null) {
            return;
        }

        $sequence = $this->followUp->handle();
        $generic = ['Follow-up 1: thank you', 'Follow-up 2: quick check-in', 'Follow-up 3: last note'];
        $names = ['1. First reply', '2. Follow-up (6 hours)', '3. Last follow-up (24 hours)'];

        foreach ($sequence->steps as $i => $step) {
            /** @var FollowUpStep $step */
            $current = $step->template()->withTrashed()->value('name');

            if (isset($names[$i]) && in_array($current, $generic, true)) {
                $step->forceFill([
                    'email_template_id' => EmailTemplate::query()->where('name', $this->prefix($profile).$names[$i])->value('id'),
                ])->save();
            }
        }
    }

    private function prefix(ServiceProfile $profile): string
    {
        return ($profile->isAgency() ? 'Berg Media' : strtok($profile->name, ' ')).' · ';
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function templates(ServiceProfile $profile): array
    {
        $agency = $profile->isAgency();
        $we = $agency ? 'we' : 'I';
        $We = ucfirst($we);
        $us = $agency ? 'us' : 'me';
        $our = $agency ? 'our' : 'my';
        $work = $agency ? 'our recent projects' : 'a few of my recent projects';

        return [
            ['1. First reply', 'Thanks for getting in touch, {{first_name}}', "Hi {{first_name}},\n\nThank you for reaching out to {{my_company}}. {$We} help businesses plan, build and improve software, websites, apps and automation around the way they actually work.\n\nSo {$we} can point you in the right direction, could you reply with a line on each:\n1. What would you like to build or improve?\n2. When would you ideally like it live?\n3. Do you have a budget range in mind?\n\nIf it's easier to talk, reply with a good time and {$we}'ll call you.\n\n{{my_signature}}"],
            ['2. Follow-up (6 hours)', 'Re: Thanks for getting in touch, {{first_name}}', "Hi {{first_name}},\n\nJust making sure my earlier note reached you. If you share a sentence about what you need, {$we}'ll come back with how {$we}'d approach it and a rough idea of time and cost: no obligation.\n\nYou can also see {$work} at {{my_website}}.\n\n{{my_signature}}"],
            ['3. Last follow-up (24 hours)', 'Re: Thanks for getting in touch, {{first_name}}', "Hi {{first_name}},\n\nI don't want to fill your inbox, so this is my last note for now. If your project is still on the cards, just reply \"yes\" and {$we}'ll pick it up from there. If the timing isn't right, no problem at all.\n\n{{my_signature}}"],
            ['4. Book a discovery call', 'Next step: a 20-minute call, {{first_name}}', "Hi {{first_name}},\n\nThanks for the details. The best next step is a short call to walk through your goals, users and must-haves, so {$we} can scope this properly.\n\nCould you send two or three times that suit you this week? {$We} can do Google Meet, Teams, Zoom or WhatsApp, whichever is easiest.\n\n{{my_signature}}"],
            ['5. Proposal sent', 'Your proposal from {{my_company}}', "Hi {{first_name}},\n\nThank you for your time on the call. As promised, the proposal is attached. It covers the scope, how {$we}'ll deliver it in stages, the timeline and the cost.\n\nIf anything needs adjusting (scope, phasing or budget), tell {$us} and {$we}'ll revise it. Once you're happy, {$we} can agree a start date.\n\n{{my_signature}}"],
            ['6. Proposal follow-up (3 days)', 'Re: Your proposal from {{my_company}}', "Hi {{first_name}},\n\nI wanted to check whether you had a chance to look at the proposal, and whether any questions came up. {$We}'re happy to walk you or your team through it on a quick call, or to adjust it to fit your budget or timeline.\n\n{{my_signature}}"],
            ['7. Check-in (nurture)', 'Still planning your project, {{first_name}}?', "Hi {{first_name}},\n\nIt's been a little while since we spoke, so a quick check-in. If your project is still in planning, {$we}'d be glad to share ideas or a rough estimate whenever it helps. Nothing to commit to.\n\nWishing you a good week,\n{{my_signature}}"],
            ['8. Welcome & kickoff (won)', 'Welcome aboard, {{first_name}}: next steps', "Hi {{first_name}},\n\nThank you for choosing {{my_company}}. {$We}'re looking forward to working with you.\n\nHere is what happens next:\n1. Kickoff call to confirm goals, users and priorities.\n2. {$We} share the plan and design direction for your sign-off.\n3. Build in short stages, with a demo at each one.\n4. Launch, then support as agreed.\n\nPlease send any logins, brand assets or documents you already have, and a time for the kickoff call.\n\n{{my_signature}}"],
            ['9. Close the loop (lost)', 'Thanks for considering {{my_company}}', "Hi {{first_name}},\n\nThank you for letting {$us} know, and for considering {$our} proposal. If you have a moment, {$we}'d really value one line on what tipped the decision; it helps {$us} improve.\n\nIf anything changes, or you need help on a future project, {$we}'re always happy to talk.\n\n{{my_signature}}"],
            ['10. Tender expression of interest', 'Expression of interest: [tender number and title]', "To the Procurement Committee,\n\n{{my_company}} would like to express interest in the above tender. {$We} deliver custom software, web platforms, mobile apps, automation and digital services, and {$we} would welcome the opportunity to bid.\n\n{$We} will obtain the bidding documents through EPADS and submit a complete, compliant bid before the closing date.\n\n{{my_signature}}"],
            ['11. Pitch to a company that is hiring', 'Your [role] opening: a faster way to get it done', "Hi {{first_name}},\n\nI saw you're hiring for this role. While you search, {{my_company}} can deliver the same work as an outside team: no recruiting delay, and you only pay for the work you need.\n\nWould a 15-minute call this week be useful to see if it's a fit?\n\n{{my_signature}}"],
        ];
    }
}
