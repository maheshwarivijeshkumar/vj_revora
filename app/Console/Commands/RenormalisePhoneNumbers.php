<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Contact\PhoneNumber;
use App\Domain\Tenancy\TenantContext;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recomputes stored phone keys after the normaliser changed.
 *
 * `phone_normalized` used to be digits-only, which meant `050 123 4567` and
 * `+971 50 123 4567` never matched each other and the same person entered both
 * ways stayed two records. It is now E.164, so existing rows hold keys that no
 * longer match what a new row would produce — and deduplication silently misses
 * them until this has run.
 *
 * Safe to run repeatedly. It touches only derived columns, and quietly: a
 * normalisation is not an edit anyone made, so announcing it would fire a
 * webhook and an audit row per record for a migration (§49, §54).
 */
final class RenormalisePhoneNumbers extends Command
{
    /** Big enough to be worth a round trip, small enough to stay in memory. */
    private const CHUNK = 500;

    protected $signature = 'leads:renormalise-phones
        {--tenant= : Limit to one workspace by id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Recompute phone_normalized, phone_type and phone_country from libphonenumber';

    private int $examined = 0;

    private int $changed = 0;

    public function handle(TenantContext $context): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->components->warn('No workspaces to process.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($tenants as $tenant) {
            // Per workspace, because the global scope is what keeps one
            // tenant's rows out of another's.
            $context->runAs($tenant, function () use ($dryRun): void {
                $this->renormaliseLeads($dryRun);
                $this->renormaliseContacts($dryRun);
            });
        }

        $verb = $dryRun ? 'would change' : 'changed';

        $this->components->info("Examined {$this->examined} records; {$verb} {$this->changed}.");

        if ($dryRun && $this->changed > 0) {
            $this->components->warn('Run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function renormaliseLeads(bool $dryRun): void
    {
        Lead::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            // Chunked by id rather than paginated: the rows being updated are
            // the rows being read, and an offset would skip records as the
            // result set shifts underneath it.
            ->chunkById(self::CHUNK, function (Collection $leads) use ($dryRun): void {
                foreach ($leads as $lead) {
                    $number = PhoneNumber::parse($lead->phone, $lead->country);

                    $this->write($lead, [
                        'phone_normalized' => $number?->e164,
                        'phone_type' => $number?->typeCode(),
                        'phone_country' => $number?->region,
                    ], $dryRun);
                }
            });
    }

    private function renormaliseContacts(bool $dryRun): void
    {
        Contact::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->chunkById(self::CHUNK, function (Collection $contacts) use ($dryRun): void {
                foreach ($contacts as $contact) {
                    $number = PhoneNumber::parse($contact->phone, $contact->country);

                    // Contacts carry no line type: those columns exist on leads
                    // because routing and messaging read them there.
                    $this->write($contact, ['phone_normalized' => $number?->e164], $dryRun);
                }
            });
    }

    /**
     * Writes the derived columns, if any of them actually differ.
     *
     * @param  array<string, string|null>  $attributes
     */
    private function write(Lead|Contact $record, array $attributes, bool $dryRun): void
    {
        $this->examined++;

        foreach ($attributes as $column => $value) {
            if ($record->getAttribute($column) === $value) {
                continue;
            }

            $this->changed++;

            if (! $dryRun) {
                $record->forceFill($attributes)->saveQuietly();
            }

            return;
        }
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        $id = $this->option('tenant');

        return Tenant::query()
            ->when($id !== null, fn ($query) => $query->whereKey($id))
            ->get();
    }
}
