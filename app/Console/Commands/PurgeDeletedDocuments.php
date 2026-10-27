<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Documents\DocumentStorage;
use App\Domain\Tenancy\TenantContext;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deletes the bytes behind documents that were removed long enough ago.
 *
 * Removing a document soft deletes the row and leaves the file, so a mistaken
 * delete can be undone — a restore that cannot return the file is not a
 * restore. That only works if something eventually clears the files up, or the
 * disk grows forever and "deleted" never means deleted, which is its own
 * problem under GDPR erasure (§88).
 *
 * Runs across every workspace, because storage is shared and a retention
 * window is not a per-tenant decision.
 */
final class PurgeDeletedDocuments extends Command
{
    /** Small: each row is a round trip to the storage driver. */
    private const CHUNK = 100;

    protected $signature = 'documents:purge
        {--days=30 : How long a removed document stays recoverable}
        {--dry-run : Report what would be purged without deleting anything}';

    protected $description = 'Permanently delete files whose document rows were removed more than --days ago';

    public function handle(TenantContext $context, DocumentStorage $storage): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->components->error('--days must be at least 1. A purge with no grace period is not a soft delete.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');
        $purged = 0;

        // Without scoping, because this sweeps the whole installation rather
        // than one workspace — the one other place this is done deliberately,
        // alongside credential lookup (ADR-010).
        $context->withoutScoping(function () use ($cutoff, $dryRun, $storage, &$purged): void {
            Document::query()
                ->onlyTrashed()
                ->where('deleted_at', '<', $cutoff)
                ->chunkById(self::CHUNK, function (Collection $documents) use ($dryRun, $storage, &$purged): void {
                    /** @var Collection<int, Document> $documents */
                    foreach ($documents as $document) {
                        $purged++;

                        if ($dryRun) {
                            $this->line("  would purge {$document->path} ({$document->original_name})");

                            continue;
                        }

                        $storage->purge($document);
                    }
                });
        });

        $this->components->info($dryRun
            ? "{$purged} document(s) would be purged (removed before {$cutoff->toDateString()})."
            : "Purged {$purged} document(s) removed before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
