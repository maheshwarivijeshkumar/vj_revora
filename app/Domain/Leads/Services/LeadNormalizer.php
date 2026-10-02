<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Leads\DataObjects\NormalizedLead;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Maps an arbitrary payload onto the common lead schema (§17).
 *
 * Providers name the same thing differently: `first_name`, `firstName`,
 * `FirstName`, `given_name`, or a Meta lead form field literally called
 * `full name`. Rather than a mapper per provider, this resolves fields by a
 * list of known aliases and keeps everything it did not recognise in
 * `metadata`, so no data is lost and core columns stay clean.
 *
 * A provider adapter that needs different behaviour passes an explicit field
 * map instead of relying on the aliases.
 */
final class LeadNormalizer
{
    /**
     * Candidate keys per canonical field, most specific first.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'firstName' => ['first_name', 'firstname', 'given_name', 'givenname', 'fname'],
        'lastName' => ['last_name', 'lastname', 'family_name', 'familyname', 'surname', 'lname'],
        'fullName' => ['full_name', 'fullname', 'name', 'contact_name'],
        'email' => ['email', 'email_address', 'emailaddress', 'e_mail', 'work_email'],
        'phone' => ['phone', 'phone_number', 'phonenumber', 'mobile', 'mobile_number', 'tel', 'telephone', 'whatsapp_number'],
        'country' => ['country', 'country_code', 'countrycode'],
        'companyName' => ['company', 'company_name', 'companyname', 'organisation', 'organization', 'business_name'],
        'jobTitle' => ['job_title', 'jobtitle', 'title', 'position', 'role'],
        'website' => ['website', 'url', 'company_website', 'site'],
        'consent' => ['consent', 'consent_given', 'opt_in', 'optin', 'marketing_consent', 'accepts_marketing', 'agree_to_terms'],
    ];

    private const UTM_KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $fieldMap  Canonical field => payload key, for
     *                                           adapters that know their own shape.
     */
    public function normalize(array $payload, array $fieldMap = []): NormalizedLead
    {
        // Lower-cased, punctuation-stripped keys, so "First Name", "first-name"
        // and "first_name" all resolve to the same alias.
        $flat = $this->flatten($payload);
        $indexed = [];

        foreach ($flat as $key => $value) {
            $indexed[$this->canonicalKey($key)] = $value;
        }

        $get = function (string $field) use ($indexed, $fieldMap): ?string {
            if (isset($fieldMap[$field])) {
                return $this->stringOrNull($indexed[$this->canonicalKey($fieldMap[$field])] ?? null);
            }

            foreach (self::ALIASES[$field] ?? [] as $alias) {
                $value = $this->stringOrNull($indexed[$this->canonicalKey($alias)] ?? null);
                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        };

        $firstName = $get('firstName');
        $lastName = $get('lastName');
        $fullName = $get('fullName');

        // A provider that only sends a full name still needs first/last for
        // templates and salutations; splitting on the last space is imperfect
        // for compound surnames, but the original is preserved in full_name.
        if ($firstName === null && $lastName === null && $fullName !== null) {
            [$firstName, $lastName] = $this->splitName($fullName);
        }

        $utm = [];
        foreach (self::UTM_KEYS as $key) {
            $value = $this->stringOrNull($indexed[$this->canonicalKey($key)] ?? null);
            if ($value !== null) {
                $utm[$key] = $value;
            }
        }

        return new NormalizedLead(
            firstName: $firstName,
            lastName: $lastName,
            fullName: $fullName ?? $this->joinName($firstName, $lastName),
            email: $get('email'),
            phone: $get('phone'),
            country: $this->countryCode($get('country')),
            companyName: $get('companyName'),
            jobTitle: $get('jobTitle'),
            website: $get('website'),
            utm: $utm,
            landingPage: $this->stringOrNull($indexed[$this->canonicalKey('landing_page')] ?? null),
            referrer: $this->stringOrNull($indexed[$this->canonicalKey('referrer')] ?? null),
            consent: $this->truthy($get('consent')),
            consentSource: $get('consent') === null ? null : 'form',
            metadata: $this->unrecognised($flat, $fieldMap),
        );
    }

    /**
     * Flattens nested payloads to dotted keys.
     *
     * Meta and LinkedIn both nest their answers; flattening means the aliases
     * work without a per-provider traversal.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload): array
    {
        $flat = Arr::dot($payload);
        $result = [];

        foreach ($flat as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
                // The leaf name alone also resolves, so "answers.0.email"
                // matches the "email" alias.
                $leaf = (string) Str::afterLast((string) $key, '.');
                if ($leaf !== (string) $key && ! isset($result[$leaf])) {
                    $result[$leaf] = $value;
                }
            }
        }

        return $result;
    }

    /** Strips punctuation and case so aliases match however a key is written. */
    private function canonicalKey(string $key): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($key)) ?? $key;
    }

    /**
     * Everything the mapping did not consume, kept verbatim (§17).
     *
     * @param  array<string, mixed>  $flat
     * @param  array<string, string>  $fieldMap
     * @return array<string, mixed>
     */
    private function unrecognised(array $flat, array $fieldMap): array
    {
        $consumed = [];

        foreach (self::ALIASES as $aliases) {
            foreach ($aliases as $alias) {
                $consumed[$this->canonicalKey($alias)] = true;
            }
        }

        foreach ([...self::UTM_KEYS, 'landing_page', 'referrer'] as $key) {
            $consumed[$this->canonicalKey($key)] = true;
        }

        foreach ($fieldMap as $key) {
            $consumed[$this->canonicalKey($key)] = true;
        }

        $extra = [];

        foreach ($flat as $key => $value) {
            if (! isset($consumed[$this->canonicalKey((string) $key)]) && $value !== null && $value !== '') {
                $extra[(string) $key] = $value;
            }
        }

        return $extra;
    }

    /**
     * Whether a consent field reads as agreement.
     *
     * Forms submit consent in every shape imaginable: a boolean, "1", "on"
     * from an HTML checkbox, "yes" from a CSV export. Anything not recognised
     * is treated as *not* consented, because §88 makes consent something that
     * must be given rather than assumed.
     */
    private function truthy(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on', 'checked'], true);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) === 0) {
            return [null, null];
        }

        if (count($parts) === 1) {
            return [$parts[0], null];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    private function joinName(?string $first, ?string $last): ?string
    {
        $joined = trim(implode(' ', array_filter([$first, $last])));

        return $joined === '' ? null : $joined;
    }

    /** Accepts a two-letter code or leaves a full country name in place. */
    private function countryCode(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $trimmed = trim($country);

        return strlen($trimmed) === 2 ? mb_strtoupper($trimmed) : null;
    }
}
