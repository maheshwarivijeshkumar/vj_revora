<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use Illuminate\Support\Str;

/**
 * Tells a consumer mailbox from a company one (§21).
 *
 * Needed because attaching a contact to a company by their email domain is
 * only sound for a domain the company actually controls. Inferring that
 * everyone on gmail.com works for the same organisation would merge unrelated
 * people into one account, which is worse than leaving them unattached.
 */
final class PersonalEmailDomain
{
    /**
     * The providers common enough to matter. Not exhaustive by design: a
     * missing entry leaves a contact unattached, which a human can fix, while
     * a wrong entry would silently split a real company's contacts apart.
     *
     * @var list<string>
     */
    private const DOMAINS = [
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'yahoo.co.uk',
        'yahoo.co.in',
        'hotmail.com',
        'hotmail.co.uk',
        'outlook.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'protonmail.com',
        'proton.me',
        'gmx.com',
        'gmx.de',
        'mail.com',
        'mail.ru',
        'yandex.com',
        'yandex.ru',
        'zoho.com',
        'qq.com',
        '163.com',
        '126.com',
        'rediffmail.com',
    ];

    public static function matches(?string $domain): bool
    {
        if ($domain === null || trim($domain) === '') {
            return false;
        }

        return in_array(Str::lower(trim($domain)), self::DOMAINS, true);
    }

    /**
     * The company-controlled host from an email address, or null when there
     * is none worth attaching to.
     */
    public static function companyDomainFrom(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        $domain = Str::lower(trim(Str::afterLast($email, '@')));

        return $domain === '' || self::matches($domain) ? null : $domain;
    }
}
