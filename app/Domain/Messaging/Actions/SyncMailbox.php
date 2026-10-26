<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\Mailboxes;
use App\Models\EmailAccount;

/**
 * Reads a mailbox's inbox for replies from leads.
 *
 * Overlaps the previous read by a few minutes, because mail can be indexed
 * after its timestamp; the Message-ID check makes the overlap free.
 */
final readonly class SyncMailbox
{
    private const FIRST_SYNC_DAYS = 3;

    private const OVERLAP_MINUTES = 10;

    public function __construct(
        private Mailboxes $mailboxes,
        private ProcessIncomingEmail $process,
    ) {}

    /**
     * @return int replies filed against leads
     */
    public function handle(EmailAccount $account): int
    {
        $startedAt = now();
        $since = $account->last_synced_at?->copy()->subMinutes(self::OVERLAP_MINUTES)
            ?? $startedAt->copy()->subDays(self::FIRST_SYNC_DAYS);
        $filed = 0;

        try {
            foreach ($this->mailboxes->for($account->provider)->fetchInbox($account, $since) as $email) {
                if ($this->process->handle($account, $email) !== null) {
                    $filed++;
                }
            }
        } catch (ProviderAuthException $e) {
            $account->forceFill(['status' => ConnectionStatus::NeedsReconnect, 'last_error' => $e->getMessage()])->save();

            return $filed;
        } catch (ProviderRequestException $e) {
            $account->forceFill(['status' => ConnectionStatus::Failing, 'last_error' => $e->getMessage()])->save();

            return $filed;
        }

        $account->forceFill([
            'status' => ConnectionStatus::Connected,
            'last_synced_at' => $startedAt,
            'last_error' => null,
        ])->save();

        return $filed;
    }
}
