<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

/**
 * How the CRM reaches a mailbox (§25).
 */
enum MailboxProvider: string
{
    /** Any mailbox: cPanel, Hostinger, Zoho, or Gmail/Outlook with an app password. */
    case Smtp = 'smtp';
    /** Gmail or Google Workspace, through the Gmail API with OAuth. */
    case Google = 'google';
    /** Outlook or Microsoft 365, through Microsoft Graph with OAuth. */
    case Microsoft = 'microsoft';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP + IMAP',
            self::Google => 'Gmail / Google Workspace',
            self::Microsoft => 'Outlook / Microsoft 365',
        };
    }

    public function usesOAuth(): bool
    {
        return $this !== self::Smtp;
    }
}
