<?php

declare(strict_types=1);

namespace App\Domain\Contact;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * A parsed phone number (§17).
 *
 * Wraps Google's libphonenumber so the rest of the application never touches
 * it directly. That matters for one reason in particular: the library answers
 * "is this number valid in its country", which no amount of digit counting can,
 * and it knows that North American numbers genuinely cannot be told apart as
 * mobile or landline — a nuance a hand-rolled check would have to fake.
 *
 * Parsing needs a region for any number written without a country code, so a
 * hint is taken in order of how much it is worth trusting: the number's own
 * prefix, then the record's country, then the workspace default.
 */
final readonly class PhoneNumber
{
    private function __construct(
        /** E.164, which is the only form worth comparing two numbers in. */
        public string $e164,
        public bool $isValid,
        public ?string $region,
        public PhoneNumberType $type,
        /** As typed, for showing back to whoever typed it. */
        public string $national,
    ) {}

    /**
     * Parses a number, or returns null when there is nothing parseable in it.
     *
     * A null means "this is not a phone number at all". An unparseable string
     * and an invalid one are different answers: `isValid` carries the second.
     */
    public static function parse(?string $input, ?string $region = null): ?self
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();
        $dialled = self::international(trim($input));
        $region = self::region($dialled, $region);

        try {
            $parsed = $util->parse($dialled, $region);
        } catch (NumberParseException) {
            return null;
        }

        return new self(
            e164: $util->format($parsed, PhoneNumberFormat::E164),
            isValid: $util->isValidNumber($parsed),
            region: $util->getRegionCodeForNumber($parsed),
            type: $util->getNumberType($parsed),
            national: $util->format($parsed, PhoneNumberFormat::NATIONAL),
        );
    }

    /**
     * Whether this number can receive a text message.
     *
     * `FIXED_LINE_OR_MOBILE` counts, and has to: across North America and much
     * of the Caribbean the numbering plan does not distinguish the two, so
     * insisting on a definite MOBILE would reject every American mobile number
     * there is.
     */
    public function isMobile(): bool
    {
        return in_array(
            $this->type,
            [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE],
            true,
        );
    }

    /**
     * Whether the type is one a genuine lead is unlikely to have given.
     *
     * A premium-rate or shared-cost number billed to the caller is not how
     * somebody asks to be contacted, and is a common filler value.
     */
    public function isSuspiciousType(): bool
    {
        return in_array(
            $this->type,
            [
                PhoneNumberType::PREMIUM_RATE,
                PhoneNumberType::SHARED_COST,
                PhoneNumberType::VOICEMAIL,
                PhoneNumberType::UAN,
            ],
            true,
        );
    }

    /**
     * A label for the line type, in words rather than an enum name.
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            PhoneNumberType::MOBILE => 'Mobile',
            PhoneNumberType::FIXED_LINE => 'Landline',
            PhoneNumberType::FIXED_LINE_OR_MOBILE => 'Mobile or landline',
            PhoneNumberType::TOLL_FREE => 'Toll free',
            PhoneNumberType::PREMIUM_RATE => 'Premium rate',
            PhoneNumberType::SHARED_COST => 'Shared cost',
            PhoneNumberType::VOIP => 'Internet calling',
            PhoneNumberType::PERSONAL_NUMBER => 'Personal number',
            PhoneNumberType::PAGER => 'Pager',
            PhoneNumberType::UAN => 'Universal access',
            PhoneNumberType::VOICEMAIL => 'Voicemail',
            default => 'Unknown',
        };
    }

    /**
     * A short, storable code for the line type.
     */
    public function typeCode(): string
    {
        return match ($this->type) {
            PhoneNumberType::MOBILE => 'mobile',
            PhoneNumberType::FIXED_LINE => 'fixed_line',
            PhoneNumberType::FIXED_LINE_OR_MOBILE => 'fixed_or_mobile',
            PhoneNumberType::TOLL_FREE => 'toll_free',
            PhoneNumberType::PREMIUM_RATE => 'premium_rate',
            PhoneNumberType::SHARED_COST => 'shared_cost',
            PhoneNumberType::VOIP => 'voip',
            PhoneNumberType::PERSONAL_NUMBER => 'personal',
            PhoneNumberType::PAGER => 'pager',
            PhoneNumberType::UAN => 'uan',
            PhoneNumberType::VOICEMAIL => 'voicemail',
            default => 'unknown',
        };
    }

    /**
     * Reduces a number to its comparable form, or null if it is not one.
     *
     * The comparison key for deduplication. E.164 is what makes
     * `050 123 4567`, `+971 50 123 4567` and `00971501234567` collapse to one
     * value — which the old digits-only normaliser could not do, because the
     * first of those keeps no country at all.
     */
    public static function normalise(?string $input, ?string $region = null): ?string
    {
        $number = self::parse($input, $region);

        if ($number === null) {
            return null;
        }

        // Kept even when invalid: an unmatchable typo should still group with
        // the same typo entered twice, and discarding it would make the record
        // unfindable by phone entirely.
        return $number->e164;
    }

    /**
     * Rewrites a dialled international prefix as a plus.
     *
     * `00` is the ITU international prefix across most of the world and is how
     * people actually write a foreign number — but libphonenumber only reads it
     * as one when it knows which country the caller is dialling from, which we
     * generally do not. Converting it here means `00971501234567` and
     * `+971501234567` reach the same record, which is the whole point of having
     * a comparison key.
     *
     * North America dials `011` rather than `00`, so that is handled too.
     */
    private static function international(string $input): string
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        // Long enough to be a country code plus a number, so a local number
        // that happens to begin 00 is not mangled into nonsense.
        if (mb_strlen($digits) < 9) {
            return $input;
        }

        foreach (['00', '011'] as $prefix) {
            if (str_starts_with($digits, $prefix)) {
                return '+'.mb_substr($digits, mb_strlen($prefix));
            }
        }

        return $input;
    }

    /**
     * The region to parse against.
     *
     * A number already carrying a country code needs none, and passing one
     * would be ignored anyway. Otherwise the record's own country is the best
     * evidence available, and the workspace default is the fallback — guessing
     * from the server's locale would silently mangle numbers for every
     * workspace that is not in that country.
     */
    private static function region(string $input, ?string $hint): ?string
    {
        $trimmed = trim($input);

        if (str_starts_with($trimmed, '+')) {
            return null;
        }

        $hint = $hint === null ? null : strtoupper(trim($hint));

        if ($hint !== null && preg_match('/^[A-Z]{2}$/', $hint) === 1) {
            return $hint;
        }

        $default = config('verification.default_region');

        return is_string($default) && $default !== '' ? strtoupper($default) : null;
    }
}
