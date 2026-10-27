<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Opportunities\Actions\ImportJobPosts;
use App\Domain\Opportunities\Actions\MatchAllWorkspaces;
use App\Domain\Opportunities\Contracts\JobSource;
use App\Domain\Opportunities\Sources\ArbeitnowSource;
use App\Domain\Opportunities\Sources\RemoteOkSource;
use App\Domain\Opportunities\Sources\RemotiveSource;
use App\Domain\Opportunities\Sources\UpworkClient;
use App\Domain\Opportunities\UpworkSearches;
use App\Domain\Tenancy\TenantContext;
use App\Models\ServiceProfile;
use App\Models\Tenant;
use App\Models\UpworkAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Pulls job posts from the public boards and connected Upwork accounts, then
 * scores them for every workspace.
 *
 * The public boards run twice a day (Remotive asks for no more than a few
 * calls a day); Upwork, where fresh jobs matter most, every two hours with
 * --upwork.
 */
final class FetchOpportunities extends Command
{
    /** @var list<class-string<JobSource>> */
    private const BOARDS = [RemotiveSource::class, RemoteOkSource::class, ArbeitnowSource::class];

    protected $signature = 'opportunities:fetch {--upwork : Only search connected Upwork accounts}';

    protected $description = 'Fetch job posts from remote job boards and Upwork, then score them against each workspace';

    public function handle(
        ImportJobPosts $import,
        MatchAllWorkspaces $match,
        TenantContext $context,
        UpworkClient $upwork,
        UpworkSearches $searches,
    ): int {
        if (! $this->option('upwork')) {
            foreach (self::BOARDS as $class) {
                /** @var JobSource $source */
                $source = app($class);

                try {
                    $counts = $import->handle($source->fetch());
                    $this->line("{$source->key()}: {$counts['created']} new, {$counts['updated']} updated, {$counts['duplicates']} duplicates skipped.");
                } catch (RuntimeException $e) {
                    // One board being down must not stop the others.
                    $this->warn("{$source->key()}: {$e->getMessage()}");
                }
            }
        }

        $this->searchUpwork($import, $context, $upwork, $searches);

        $this->info('New matches: '.$match->handle());

        return self::SUCCESS;
    }

    private function searchUpwork(ImportJobPosts $import, TenantContext $context, UpworkClient $upwork, UpworkSearches $searches): void
    {
        if (! $upwork->isConfigured()) {
            return;
        }

        /** @var Collection<int, UpworkAccount> $accounts */
        $accounts = $context->withoutScoping(fn (): Collection => UpworkAccount::query()
            ->where('status', '!=', ConnectionStatus::NeedsReconnect->value)
            ->get());

        foreach ($accounts as $account) {
            $tenant = $context->withoutScoping(fn (): ?Tenant => Tenant::query()->find($account->tenant_id));

            if ($tenant === null || ! $tenant->isActive()) {
                continue;
            }

            $context->runAs($tenant, function () use ($account, $import, $upwork, $searches): void {
                try {
                    foreach (ServiceProfile::query()->get() as $profile) {
                        foreach ($searches->for($profile) as $expression) {
                            $counts = $import->handle($upwork->search($account, $expression));
                            $this->line("upwork ({$profile->name}): {$counts['created']} new, {$counts['updated']} updated.");
                        }
                    }

                    $account->forceFill(['status' => ConnectionStatus::Connected, 'last_error' => null, 'last_synced_at' => now()])->save();
                } catch (ProviderAuthException $e) {
                    $account->forceFill(['status' => ConnectionStatus::NeedsReconnect, 'last_error' => $e->getMessage()])->save();
                    $this->warn('upwork: '.$e->getMessage());
                } catch (ProviderRequestException $e) {
                    $account->forceFill(['status' => ConnectionStatus::Failing, 'last_error' => $e->getMessage()])->save();
                    $this->warn('upwork: '.$e->getMessage());
                }
            });
        }
    }
}
