<?php

declare(strict_types=1);

namespace App\Domain\Bulk\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Bulk\Enums\BulkAction;
use App\Domain\Bulk\Enums\BulkStatus;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadVerifier;
use App\Models\BulkOperation;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Applies one action to many leads (§113).
 *
 * Iterates models in chunks rather than issuing a single mass UPDATE. A mass
 * update is faster and wrong: it bypasses observers, so the change would reach
 * no audit row, fire no webhook and skip the normalised columns the save hooks
 * maintain. A bulk edit has to mean the same thing as the same edit made one row
 * at a time.
 *
 * One record failing does not fail the operation. Twenty-four selected rows
 * where two have since been deleted should update twenty-two and say so, not
 * refuse the lot.
 */
final class RunBulkLeadAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly LeadVerifier $verifier,
    ) {}

    public function handle(BulkOperation $operation): BulkOperation
    {
        $operation->forceFill(['status' => BulkStatus::Running])->save();

        $errors = [];
        $processed = 0;
        $failed = 0;

        // Resolved up front: a missing tag is a bad request, not something to
        // discover halfway through a thousand records.
        $tag = in_array($operation->action, [BulkAction::AddTag, BulkAction::RemoveTag], true)
            ? $this->resolveTag($operation)
            : null;

        foreach (array_chunk($operation->ids, BulkOperation::CHUNK) as $chunk) {
            // Re-queried through the tenant scope, so an id from another
            // workspace simply does not resolve and there is no ownership check
            // to forget. Rows deleted since the selection are counted as
            // skipped rather than silently dropped from the total.
            $leads = Lead::query()->whereIn('id', $chunk)->get();

            foreach ($chunk as $id) {
                $lead = $leads->firstWhere('id', $id);
                $processed++;

                if (! $lead instanceof Lead) {
                    $failed++;
                    $errors[] = "Lead #{$id} no longer exists.";

                    continue;
                }

                try {
                    $this->apply($operation, $lead, $tag);
                } catch (Throwable $e) {
                    $failed++;
                    $errors[] = "Lead #{$id}: {$e->getMessage()}";
                }
            }

            // Written per chunk, so a progress poll sees movement during a long
            // run rather than nothing until the end.
            $operation->forceFill([
                'processed' => $processed,
                'failed' => $failed,
            ])->save();
        }

        $operation->forceFill([
            'status' => $failed === 0
                ? BulkStatus::Completed
                : BulkStatus::PartiallyCompleted,
            'processed' => $processed,
            'failed' => $failed,
            // Capped: the point is to explain a partial result, and a thousand
            // identical messages explain no more than ten do.
            'errors' => $errors === [] ? null : array_slice($errors, 0, 10),
            'finished_at' => now(),
        ])->save();

        // One audit row for the operation, in addition to the per-record rows
        // the observers write. Without it the trail shows fifty edits and not
        // the single decision that caused them (§54).
        $this->audit->record(
            AuditAction::LeadUpdated,
            $operation,
            after: [
                'bulk_action' => $operation->action->value,
                'total' => $operation->total,
                'succeeded' => $processed - $failed,
                'failed' => $failed,
            ],
        );

        return $operation;
    }

    private function apply(BulkOperation $operation, Lead $lead, ?Tag $tag): void
    {
        match ($operation->action) {
            BulkAction::Assign => $this->assign($lead, $operation),
            BulkAction::ChangeStatus => $this->changeStatus($lead, $operation),
            BulkAction::AddTag => $this->tag($lead, $this->required($tag), attach: true),
            BulkAction::RemoveTag => $this->tag($lead, $this->required($tag), attach: false),
            // With the DNS lookup on: a bulk check is deliberate, runs on a
            // queue above 200 records, and is the one place the latency is
            // worth paying for.
            BulkAction::Verify => $this->verifier->apply($lead, checkMx: true),
            BulkAction::Delete => $lead->delete(),
        };
    }

    private function required(?Tag $tag): Tag
    {
        if (! $tag instanceof Tag) {
            throw new RuntimeException('No tag was given.');
        }

        return $tag;
    }

    private function assign(Lead $lead, BulkOperation $operation): void
    {
        $ownerId = $operation->payload['owner_id'] ?? null;

        // Scoped lookup, so a user id from another workspace cannot be assigned
        // ownership of this one's leads.
        if ($ownerId !== null && User::query()->whereKey($ownerId)->doesntExist()) {
            throw new RuntimeException('That user is not in this workspace.');
        }

        $lead->owner_id = $ownerId === null ? null : (int) $ownerId;
        $lead->save();
    }

    private function changeStatus(Lead $lead, BulkOperation $operation): void
    {
        $status = LeadStatus::from((string) ($operation->payload['status'] ?? ''));

        $lead->status = $status;

        // Stamped once, the first time the lead qualifies, exactly as a single
        // edit does — otherwise a bulk requalify would reset time-to-qualify
        // reporting across the whole selection.
        if ($status === LeadStatus::Qualified && $lead->qualified_at === null) {
            $lead->qualified_at = now();
        }

        $lead->save();
    }

    private function tag(Lead $lead, Tag $tag, bool $attach): void
    {
        if ($attach) {
            // syncWithoutDetaching, so applying a tag twice is not an error and
            // does not duplicate the pivot row.
            $lead->tags()->syncWithoutDetaching([$tag->id]);

            return;
        }

        $lead->tags()->detach($tag->id);
    }

    /**
     * Resolves the tag once for the whole operation, not once per record.
     *
     * Returned rather than cached on the service: this class is resolved from
     * the container, and a tag left over from an earlier operation would be
     * applied to the next one.
     */
    private function resolveTag(BulkOperation $operation): Tag
    {
        $name = trim((string) ($operation->payload['tag'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('No tag was given.');
        }

        return Tag::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );
    }
}
