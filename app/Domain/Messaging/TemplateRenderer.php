<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\ServiceProfile;

/**
 * Fills {{placeholders}} in a template from the lead, the sender, and the
 * business it is sent as (§26).
 *
 * A fixed allowlist rather than arbitrary attribute access, so a template
 * cannot reach a field it should not print. Unknown placeholders render as
 * nothing instead of leaking their braces into an email.
 */
final class TemplateRenderer
{
    /** @var array<string, string> placeholder => what it is, for the editor's help text */
    public const PLACEHOLDERS = [
        'first_name' => "Lead's first name",
        'last_name' => "Lead's last name",
        'full_name' => "Lead's full name",
        'company_name' => "Lead's company",
        'job_title' => "Lead's job title",
        'email' => "Lead's email",
        'sender_name' => 'Your name (the mailbox sender name)',
        'sender_email' => 'Your email address',
        'my_company' => 'Your business name (from the service profile)',
        'my_website' => 'Your website',
        'my_phone' => 'Your phone / WhatsApp',
        'my_signature' => 'Your full sign-off',
    ];

    public function render(string $template, Lead $lead, ?EmailAccount $sender = null, ?ServiceProfile $profile = null): string
    {
        $profile ??= ServiceProfile::default();

        $values = [
            // A greeting with a blank name reads worse than a generic one.
            'first_name' => $lead->first_name ?: 'there',
            'last_name' => (string) $lead->last_name,
            'full_name' => $lead->displayName(),
            'company_name' => (string) $lead->company_name,
            'job_title' => (string) $lead->job_title,
            'email' => (string) $lead->email,
            'sender_name' => (string) ($sender->from_name ?? config('app.name')),
            'sender_email' => (string) $sender?->email,
            'my_company' => (string) ($profile->name ?? config('app.name')),
            'my_website' => (string) $profile?->website,
            'my_phone' => (string) $profile?->phone,
            'my_signature' => (string) ($profile->signature ?? $sender->from_name ?? config('app.name')),
        ];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $m): string => $values[mb_strtolower($m[1])] ?? '',
            $template,
        );
    }
}
