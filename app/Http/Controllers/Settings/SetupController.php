<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Ai\Writer;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\LeadAdsProviders;
use App\Domain\Opportunities\Sources\UpworkClient;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\IntegrationConnection;
use App\Models\JobPost;
use App\Models\ServiceProfile;
use App\Models\Tender;
use App\Models\UpworkAccount;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One page that says what is connected, what is not, and what to do next.
 *
 * Every check reads real state (a connection row, a recent fetch, the
 * scheduler's heartbeat) rather than configuration alone, so a green tick
 * means it is actually working.
 */
final class SetupController extends Controller
{
    /** Written by the every-minute scheduled command. */
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    public function __invoke(LeadAdsProviders $providers, UpworkClient $upwork, Writer $writer): Response
    {
        $profiles = ServiceProfile::query()->get();
        $mailbox = EmailAccount::query()->orderByDesc('is_default')->first();
        $whatsApp = WhatsAppAccount::query()->first();
        $upworkAccount = UpworkAccount::query()->first();
        $connections = IntegrationConnection::query()->get()->groupBy(fn (IntegrationConnection $c): string => $c->provider->value);

        $steps = [
            $this->step(
                'profiles',
                'Your services and resume',
                $profiles->isNotEmpty() && ! $profiles->contains(fn (ServiceProfile $p): bool => str_contains((string) $p->about, 'Paste the full resume here')),
                $profiles->isEmpty()
                    ? 'Create the Berg Media Digital and Vijesh profiles and install the email playbook.'
                    : 'Paste your full resume into your profile: proposals are written only from what it says.',
                '/settings/services',
                $profiles->isEmpty() ? 'Set up profiles' : 'Edit profiles',
            ),
            $this->step(
                'mailbox',
                'Email (send and receive)',
                $mailbox !== null && $mailbox->status !== ConnectionStatus::NeedsReconnect,
                $mailbox === null ? 'Connect Gmail, Microsoft 365, or any mailbox by SMTP + IMAP.' : ($mailbox->last_error ?? $mailbox->email.' is connected.'),
                '/settings/email',
                $mailbox === null ? 'Connect' : 'Manage',
            ),
            ...array_map(fn (LeadAdsPlatform $platform): array => $this->step(
                $platform->value,
                $platform->label(),
                ($connections[$platform->value] ?? collect())->contains(fn (IntegrationConnection $c): bool => $c->status === ConnectionStatus::Connected),
                ! $providers->for($platform)->isConfigured()
                    ? 'Add the app ID and secret to the server, then sign in. See the guide for the app approval steps.'
                    : (($connections[$platform->value] ?? collect())->isEmpty()
                        ? 'Sign in and choose the '.$platform->accountNoun().' whose lead forms to import.'
                        : ($connections[$platform->value] ?? collect())->count().' '.$platform->accountNoun().'(s) connected.'),
                '/settings/integrations',
                'Open',
            ), LeadAdsPlatform::cases()),
            $this->step(
                'whatsapp',
                'WhatsApp Business',
                $whatsApp !== null && $whatsApp->status === ConnectionStatus::Connected,
                $whatsApp === null ? 'Add your Cloud API phone number ID, token and approved welcome template.' : ($whatsApp->last_error ?? 'Connected.'),
                '/settings/whatsapp',
                $whatsApp === null ? 'Connect' : 'Manage',
            ),
            $this->step(
                'upwork',
                'Upwork job search',
                $upworkAccount !== null && $upworkAccount->status === ConnectionStatus::Connected,
                ! $upwork->isConfigured()
                    ? 'Request an Upwork API key with "Read marketplace Job Postings", then add it to the server.'
                    : ($upworkAccount === null ? 'Sign in to Upwork to start finding jobs.' : ($upworkAccount->last_error ?? 'Connected, read-only.')),
                $upwork->isConfigured() ? '/settings/upwork/connect' : null,
                $upworkAccount === null ? 'Connect Upwork' : 'Reconnect',
            ),
            $this->step(
                'ai',
                'AI writing and reply scoring',
                $writer->generator() !== null,
                $writer->generator() === null
                    ? 'Add an Anthropic, OpenAI or Gemini key to the server (AI_PROVIDER). Until then, drafts use templates and replies are scored by keywords.'
                    : 'Drafting with '.$writer->generator()->name().'.',
                null,
                null,
            ),
            $this->step(
                'scheduler',
                'Scheduler running',
                Cache::get(self::HEARTBEAT_KEY) !== null,
                'Runs follow-ups every minute, reads inboxes every 5 minutes, syncs lead ads hourly and fetches tenders and jobs twice a day. Start it with "php artisan schedule:work" (or a cron for schedule:run).',
                null,
                null,
            ),
            $this->step(
                'queue',
                'Queue worker running',
                $this->queueHealthy(),
                'Sends the emails and WhatsApp messages. Run: php artisan queue:work --queue=verification,webhooks,integrations,messaging,bulk,default',
                null,
                null,
            ),
            $this->step(
                'tenders',
                'Pakistan tenders',
                Tender::query()->where('last_seen_at', '>=', now()->subDay())->exists(),
                'Fetched from PPRA at 8 AM and 4 PM Pakistan time. Run "php artisan tenders:fetch" for the first pull.',
                '/tenders',
                'View tenders',
            ),
            $this->step(
                'boards',
                'Remote job boards',
                JobPost::query()->where('source', '!=', 'upwork')->where('last_seen_at', '>=', now()->subDay())->exists(),
                'Remotive, Remote OK and Arbeitnow, twice a day. Run "php artisan opportunities:fetch" for the first pull.',
                '/opportunities',
                'View opportunities',
            ),
        ];

        return Inertia::render('settings/Setup', [
            'steps' => $steps,
            'done' => count(array_filter($steps, fn (array $s): bool => $s['done'])),
        ]);
    }

    /**
     * @return array{key: string, title: string, done: bool, detail: string, href: string|null, action: string|null}
     */
    private function step(string $key, string $title, bool $done, string $detail, ?string $href, ?string $action): array
    {
        return compact('key', 'title', 'done', 'detail', 'href', 'action');
    }

    /** No job has waited more than ten minutes, on the database queue. */
    private function queueHealthy(): bool
    {
        if (config('queue.default') !== 'database') {
            return true;
        }

        $oldest = DB::table(config('queue.connections.database.table', 'jobs'))->min('available_at');

        return $oldest === null || (int) $oldest > now()->subMinutes(10)->getTimestamp();
    }
}
