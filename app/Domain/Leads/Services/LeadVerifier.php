<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Contact\PhoneNumber;
use App\Domain\Crm\PersonalEmailDomain;
use App\Domain\Leads\DataObjects\VerificationResult;
use App\Domain\Leads\Enums\VerificationStatus;
use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Decides whether a lead's contact details are real (§18, §59).
 *
 * This is the "are these genuine" question, and it is deliberately independent
 * of where the lead came from: a lead from an authorized provider feed can
 * still carry a typo'd address, and one typed in by hand can be perfect.
 * Provenance is a separate signal, shown separately (§2).
 *
 * Phone validation uses Google's libphonenumber metadata, so every country's
 * numbering plan is covered and a mobile can be told from a landline wherever
 * the plan distinguishes them — which North America's does not, and the check
 * is honest about that rather than guessing.
 *
 * Every other check is local except the MX lookup. That is on purpose — a form must
 * not wait on somebody else's DNS resolver — and it is also the honest limit of
 * what this can tell you: it proves an address is *addressable*, not that a
 * person reads it. Proving the latter needs a commercial validator or a sent
 * message, and neither is something to pretend about.
 *
 * Confidence starts at 100 and loses points per finding, so the number is
 * always explainable by the list beside it.
 */
final class LeadVerifier
{
    /**
     * What each finding costs.
     *
     * Weighted by how much it actually predicts. A syntactically broken address
     * is fatal; a free mailbox barely matters for B2C and matters a lot for
     * enterprise sales, which is why it is a small deduction and a visible
     * reason rather than a rejection.
     */
    private const PENALTIES = [
        'email.missing_and_no_phone' => 100,
        'email.malformed' => 100,
        'email.no_mx' => 70,
        'email.disposable' => 60,
        'email.placeholder' => 60,
        'email.gibberish' => 30,
        'email.role' => 20,
        'email.free_provider' => 5,
        'phone.unparseable' => 35,
        'phone.invalid_for_country' => 35,
        'phone.suspicious_type' => 30,
        'phone.unknown_country' => 10,
        'phone.not_mobile' => 5,
        'name.placeholder' => 25,
        'name.missing' => 10,
        'consent.absent' => 15,
    ];

    public function verify(Lead $lead, ?bool $checkMx = null): VerificationResult
    {
        $checkMx ??= (bool) config('verification.check_mx');

        $findings = [
            ...$this->checkEmail($lead, $checkMx),
            ...$this->checkPhone($lead),
            ...$this->checkName($lead),
            ...$this->checkConsent($lead),
        ];

        $confidence = 100;

        foreach ($findings as $finding) {
            $confidence -= self::PENALTIES[$finding['check']] ?? 0;
        }

        $confidence = max(0, min(100, $confidence));

        return new VerificationResult(
            status: $this->band($confidence),
            confidence: $confidence,
            findings: $findings,
        );
    }

    /**
     * Applies a verification to the lead and stores it.
     */
    public function apply(Lead $lead, ?bool $checkMx = null): VerificationResult
    {
        $result = $this->verify($lead, $checkMx);

        $number = PhoneNumber::parse($lead->phone, $lead->country);

        $lead->forceFill([
            'verification_status' => $result->status,
            'verification_confidence' => $result->confidence,
            'verification_findings' => $result->findings,
            'verified_at' => now(),
            // Stored because "can this lead be texted" decides which channel
            // reaches them, and recomputing it per message would mean parsing
            // the number on every send.
            'phone_type' => $number?->typeCode(),
            'phone_country' => $number?->region,
        ])->save();

        return $result;
    }

    /**
     * @return list<array{check: string, verdict: string, detail: string}>
     */
    private function checkEmail(Lead $lead, bool $checkMx): array
    {
        $email = trim((string) $lead->email);

        if ($email === '') {
            // Not a problem in itself: a lead reachable by phone is reachable.
            return $lead->phone === null || trim($lead->phone) === ''
                ? [$this->fail('email.missing_and_no_phone', 'No email address and no phone number.')]
                : [];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [$this->fail('email.malformed', "“{$email}” is not a valid address.")];
        }

        $findings = [];
        $local = Str::lower(Str::before($email, '@'));
        $domain = Str::lower(Str::afterLast($email, '@'));

        if (in_array($domain, (array) config('verification.disposable_domains', []), true)) {
            $findings[] = $this->fail(
                'email.disposable',
                "{$domain} is a throwaway mailbox provider.",
            );
        }

        if ($this->isPlaceholder($local)) {
            $findings[] = $this->fail('email.placeholder', "“{$local}@” looks like filler.");
        } elseif ($this->looksRandom($local)) {
            // Separate from placeholder: random strings are what bots submit,
            // filler is what humans type to skip a field.
            $findings[] = $this->warn('email.gibberish', "“{$local}@” does not read like a name.");
        }

        if (in_array($local, (array) config('verification.role_addresses', []), true)) {
            $findings[] = $this->warn(
                'email.role',
                "{$local}@ is a shared inbox rather than a person.",
            );
        }

        if (PersonalEmailDomain::matches($domain)) {
            $findings[] = $this->warn(
                'email.free_provider',
                'A personal mailbox, not a company one.',
            );
        }

        // Last, because it is the only check that leaves the machine, and it is
        // pointless once the address is known to be malformed.
        if ($checkMx && ! $this->hasMailExchanger($domain)) {
            $findings[] = $this->fail(
                'email.no_mx',
                "{$domain} does not accept mail.",
            );
        }

        return $findings;
    }

    /**
     * Validates the number against its own country's numbering plan.
     *
     * Google's metadata rather than digit counting, because "is this a real
     * number in this country" is a different question from "does it have
     * between seven and fifteen digits", and only the first is useful. A UAE
     * mobile and a Dubai landline are both twelve digits with the same prefix;
     * only one of them is reachable by SMS.
     *
     * @return list<array{check: string, verdict: string, detail: string}>
     */
    private function checkPhone(Lead $lead): array
    {
        $raw = trim((string) $lead->phone);

        if ($raw === '') {
            return [];
        }

        $number = PhoneNumber::parse($raw, $lead->country);

        if ($number === null) {
            return [$this->fail(
                'phone.unparseable',
                "“{$raw}” is not a phone number. Add a country code, such as +971.",
            )];
        }

        if (! $number->isValid) {
            $where = $number->region === null
                ? 'any country'
                : $number->region;

            // Says which country it was judged against: the usual cause is a
            // number written without a country code and parsed against the
            // wrong one, and the rep can only fix that if they are told.
            return [$this->fail(
                'phone.invalid_for_country',
                "“{$raw}” is not a valid number in {$where}.",
            )];
        }

        $findings = [];

        if ($number->isSuspiciousType()) {
            $findings[] = $this->fail(
                'phone.suspicious_type',
                "“{$raw}” is a {$number->typeLabel()} line, not a personal one.",
            );
        } elseif (! $number->isMobile()) {
            // A warning, not a failure: a landline is perfectly contactable,
            // it just cannot be texted, and whole industries answer theirs.
            $findings[] = $this->warn(
                'phone.not_mobile',
                "A {$number->typeLabel()} — calls will reach it, messages will not.",
            );
        }

        if ($number->region === null) {
            $findings[] = $this->warn(
                'phone.unknown_country',
                'The country this number belongs to could not be determined.',
            );
        }

        return $findings;
    }

    /**
     * @return list<array{check: string, verdict: string, detail: string}>
     */
    private function checkName(Lead $lead): array
    {
        $name = trim((string) $lead->full_name);

        if ($name === '') {
            // Minor: plenty of real leads arrive as an address alone, and a
            // missing name is something outreach can work around.
            return [$this->warn('name.missing', 'No name given.')];
        }

        if ($this->isPlaceholder(Str::lower($name))) {
            return [$this->fail('name.placeholder', "“{$name}” looks like filler.")];
        }

        return [];
    }

    /**
     * @return list<array{check: string, verdict: string, detail: string}>
     */
    private function checkConsent(Lead $lead): array
    {
        if ($lead->consent) {
            return [];
        }

        // Not about whether the details are real, but about whether they can be
        // used, which is the same question from the rep's point of view (§88).
        return [$this->warn(
            'consent.absent',
            'No recorded agreement to be contacted.',
        )];
    }

    /**
     * Whether a value is one of the things people type to skip a field.
     */
    private function isPlaceholder(string $value): bool
    {
        $placeholders = (array) config('verification.placeholders', []);

        // Whole-value match, so a real surname like "Tester" survives.
        return in_array($value, $placeholders, true);
    }

    /**
     * Rough gibberish detection for a mailbox name.
     *
     * Deliberately conservative: the cost of flagging a real person is higher
     * than the cost of missing a bot, so this only fires on strings with almost
     * no vowels or an implausible run of consonants.
     */
    private function looksRandom(string $local): bool
    {
        $letters = preg_replace('/[^a-z]/', '', $local) ?? '';

        if (mb_strlen($letters) < 8) {
            return false;
        }

        $vowels = preg_match_all('/[aeiou]/', $letters);

        if ($vowels === 0) {
            return true;
        }

        return $vowels / mb_strlen($letters) < 0.2
            || preg_match('/[bcdfghjklmnpqrstvwxyz]{6,}/', $letters) === 1;
    }

    /**
     * Whether the domain publishes anywhere to deliver mail.
     *
     * Falls back to an A record, because a host with no MX but an address still
     * accepts mail under RFC 5321. Treated as "we could not tell" rather than
     * "no" on a resolver failure: penalising a lead for our own DNS trouble
     * would be worse than missing a bad domain.
     */
    private function hasMailExchanger(string $domain): bool
    {
        if ($domain === '' || ! str_contains($domain, '.')) {
            return false;
        }

        try {
            return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        } catch (\Throwable) {
            return true;
        }
    }

    private function band(int $confidence): VerificationStatus
    {
        $thresholds = (array) config('verification.thresholds', []);

        return match (true) {
            $confidence >= ($thresholds['valid'] ?? 80) => VerificationStatus::Valid,
            $confidence >= ($thresholds['risky'] ?? 40) => VerificationStatus::Risky,
            default => VerificationStatus::Invalid,
        };
    }

    /**
     * @return array{check: string, verdict: string, detail: string}
     */
    private function fail(string $check, string $detail): array
    {
        return ['check' => $check, 'verdict' => 'fail', 'detail' => $detail];
    }

    /**
     * @return array{check: string, verdict: string, detail: string}
     */
    private function warn(string $check, string $detail): array
    {
        return ['check' => $check, 'verdict' => 'warn', 'detail' => $detail];
    }
}
