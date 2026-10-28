<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\LeadAdsProviders;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Messaging\Enums\MailboxProvider;
use App\Domain\Messaging\Mailboxes;
use App\Domain\Operations\Enums\EstimateStatus;
use App\Domain\Operations\Enums\InvoiceStatus;
use App\Domain\Operations\Enums\ProjectStatus;
use App\Domain\Operations\Money;
use App\Domain\Opportunities\Sources\UpworkClient;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\EmailAccount;
use App\Models\Estimate;
use App\Models\IntegrationConnection;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\Task;
use App\Models\Tender;
use App\Models\UpworkAccount;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace home.
 *
 * Every number is a count of records this workspace actually has, and every
 * card opens the module that owns those records.
 */
final class DashboardController extends Controller
{
    public function __invoke(LeadAdsProviders $providers, UpworkClient $upwork, Mailboxes $mailboxes): Response
    {
        return Inertia::render('Dashboard', [
            'metrics' => $this->metrics(),
            'pipeline' => $this->pipeline(),
            'activity' => $this->activity(),
            'leads' => $this->recentLeads(),
            'tasks' => $this->tasks(),
            'people' => $this->people(),
            'connections' => $this->connections($providers, $upwork, $mailboxes),
            'work' => $this->work(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, value: int, href: string}>
     */
    private function metrics(): array
    {
        return [
            [
                'key' => 'leads',
                'label' => 'Open leads',
                'value' => Lead::query()->master()->open()->count(),
                'href' => '/leads',
            ],
            [
                'key' => 'qualified',
                'label' => 'Qualified leads',
                'value' => Lead::query()->master()->where('status', LeadStatus::Qualified)->count(),
                'href' => '/leads',
            ],
            [
                'key' => 'deals',
                'label' => 'Open deals',
                'value' => Deal::query()->open()->count(),
                'href' => '/deals',
            ],
            [
                'key' => 'tenders',
                'label' => 'Open IT tenders',
                'value' => Tender::query()->open()->where('is_it_related', true)->count(),
                'href' => '/tenders',
            ],
        ];
    }

    /**
     * @return array{name: string|null, href: string, stages: list<array{key: string, name: string, count: int, won: bool, lost: bool}>}
     */
    private function pipeline(): array
    {
        $pipeline = Pipeline::query()->where('is_default', true)->with('stages')->first()
            ?? Pipeline::query()->with('stages')->first();

        if ($pipeline === null) {
            return ['name' => null, 'href' => '/deals', 'stages' => []];
        }

        $counts = Deal::query()
            ->where('pipeline_id', $pipeline->id)
            ->selectRaw('pipeline_stage_id, COUNT(*) as total')
            ->groupBy('pipeline_stage_id')
            ->pluck('total', 'pipeline_stage_id');

        return [
            'name' => $pipeline->name,
            'href' => '/deals',
            'stages' => $pipeline->stages->map(fn (PipelineStage $stage): array => [
                'key' => $stage->key,
                'name' => $stage->name,
                'count' => (int) ($counts[$stage->id] ?? 0),
                'won' => (bool) $stage->is_won,
                'lost' => (bool) $stage->is_lost,
            ])->values()->all(),
        ];
    }

    /**
     * Leads captured on each of the last seven days, oldest first.
     *
     * @return list<array{date: string, label: string, count: int}>
     */
    private function activity(): array
    {
        $start = now()->startOfDay()->subDays(6);

        $counts = Lead::query()
            ->master()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->countBy(fn (Lead $lead): string => $lead->created_at->toDateString());

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'label' => $day->format('D'),
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return list<array{id: int, name: string, company: string|null, status: string, tone: string, href: string, at: string|null}>
     */
    private function recentLeads(): array
    {
        return Lead::query()
            ->master()
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'name' => $lead->displayName(),
                'company' => $lead->company_name,
                'status' => $lead->status->label(),
                'tone' => $lead->status->tone(),
                'href' => '/leads/'.$lead->id,
                'at' => $lead->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, status: string, due_at: string|null, href: string|null}>
     */
    private function tasks(): array
    {
        return Task::query()
            ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->limit(5)
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->label(),
                'due_at' => $task->due_at?->toIso8601String(),
                'href' => $this->subjectHref($task->subject_type, $task->subject_id),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, open_leads: int}>
     */
    private function people(): array
    {
        $counts = Lead::query()
            ->master()
            ->open()
            ->whereNotNull('owner_id')
            ->selectRaw('owner_id, COUNT(*) as total')
            ->groupBy('owner_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'owner_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $users = User::query()->whereIn('id', $counts->keys())->get()->keyBy('id');

        return $counts
            ->map(function (mixed $total, int|string $ownerId) use ($users): ?array {
                $user = $users->get((int) $ownerId);

                if (! $user instanceof User) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'open_leads' => (int) $total,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The job board: projects, money, today's visits, and what needs a person.
     *
     * @return array{
     *     metrics: list<array{label: string, value: string, href: string}>,
     *     schedule: list<array{id: int, title: string, at: string, crew: string|null, location: string|null}>,
     *     attention: list<array{label: string, href: string}>
     * }
     */
    private function work(): array
    {
        $revenue = (float) Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total');

        $outstanding = (float) Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->sum(DB::raw('total - paid_amount'));

        $waiting = Estimate::query()->where('status', EstimateStatus::Sent)->count();
        $overdue = Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_on', '<', today())
            ->count();

        $attention = [];

        if ($waiting > 0) {
            $attention[] = [
                'label' => $waiting.' '.($waiting === 1 ? 'estimate is' : 'estimates are').' waiting on a reply',
                'href' => '/estimates',
            ];
        }

        if ($overdue > 0) {
            $attention[] = [
                'label' => $overdue.' '.($overdue === 1 ? 'invoice is' : 'invoices are').' past the due date',
                'href' => '/invoices',
            ];
        }

        return [
            'metrics' => [
                [
                    'label' => 'Active projects',
                    'value' => (string) Project::query()->where('status', ProjectStatus::Active)->count(),
                    'href' => '/projects',
                ],
                [
                    'label' => 'Leads this week',
                    'value' => (string) Lead::query()->master()->where('created_at', '>=', now()->startOfWeek())->count(),
                    'href' => '/leads',
                ],
                [
                    'label' => 'Revenue this month',
                    'value' => Money::display($revenue),
                    'href' => '/invoices',
                ],
                [
                    'label' => 'Outstanding',
                    'value' => Money::display($outstanding),
                    'href' => '/invoices',
                ],
            ],
            'schedule' => ScheduleEntry::query()
                ->with('crewMember')
                ->whereDate('starts_at', today())
                ->orderBy('starts_at')
                ->limit(6)
                ->get()
                ->map(fn (ScheduleEntry $entry): array => [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'at' => $entry->starts_at->format('g:i A'),
                    'crew' => $entry->crewMember?->name,
                    'location' => $entry->location,
                ])
                ->all(),
            'attention' => $attention,
        ];
    }

    /**
     * @return list<array{key: string, label: string, state: string, detail: string, href: string}>
     */
    private function connections(LeadAdsProviders $providers, UpworkClient $upwork, Mailboxes $mailboxes): array
    {
        $rows = [];

        foreach (LeadAdsPlatform::cases() as $platform) {
            $connected = IntegrationConnection::query()
                ->where('provider', $platform)
                ->where('status', ConnectionStatus::Connected)
                ->exists();
            $ready = $providers->for($platform)->isConfigured();

            $rows[] = [
                'key' => $platform->value,
                'label' => $platform === LeadAdsPlatform::Meta ? 'Facebook & Instagram' : 'LinkedIn',
                'state' => $connected ? 'connected' : ($ready ? 'sign_in' : 'setup'),
                'detail' => $connected
                    ? 'Lead ads are syncing.'
                    : ($ready ? 'Sign in to choose the account.' : 'Add the app ID and secret, then sign in.'),
                'href' => '/settings/integrations',
            ];
        }

        $upworkAccount = UpworkAccount::query()->first();
        $upworkConnected = $upworkAccount !== null && $upworkAccount->status === ConnectionStatus::Connected;

        $rows[] = [
            'key' => 'upwork',
            'label' => 'Upwork',
            'state' => $upworkConnected ? 'connected' : ($upwork->isConfigured() ? 'sign_in' : 'setup'),
            'detail' => $upworkConnected
                ? 'Jobs are being read.'
                : ($upwork->isConfigured() ? 'Sign in to start the job search.' : 'Add an approved Upwork API key.'),
            'href' => $upwork->isConfigured() ? '/settings/upwork/connect' : '/settings/setup',
        ];

        $whatsapp = WhatsAppAccount::query()->first();
        $whatsappConnected = $whatsapp !== null && $whatsapp->status === ConnectionStatus::Connected;

        $rows[] = [
            'key' => 'whatsapp',
            'label' => 'WhatsApp Business',
            'state' => $whatsappConnected ? 'connected' : 'setup',
            'detail' => $whatsappConnected
                ? 'Chat from any lead that has a phone number.'
                : 'Add the phone number ID and permanent token.',
            'href' => '/settings/whatsapp',
        ];

        $googleConnected = EmailAccount::query()
            ->where('provider', MailboxProvider::Google)
            ->where('status', ConnectionStatus::Connected)
            ->exists();
        $googleReady = $mailboxes->oauth(MailboxProvider::Google)->isConfigured();

        $rows[] = [
            'key' => 'google',
            'label' => 'Google',
            'state' => $googleConnected ? 'connected' : ($googleReady ? 'sign_in' : 'setup'),
            'detail' => $googleConnected
                ? 'Gmail is sending and reading replies.'
                : ($googleReady ? 'Sign in with Google.' : 'Add the Google client ID and secret.'),
            'href' => '/settings/email',
        ];

        return $rows;
    }

    private function subjectHref(?string $type, ?int $id): ?string
    {
        if ($type === null || $id === null) {
            return null;
        }

        return match ($type) {
            Lead::class => '/leads/'.$id,
            Deal::class => '/deals/'.$id,
            Contact::class => '/contacts/'.$id,
            Company::class => '/companies/'.$id,
            default => null,
        };
    }
}
