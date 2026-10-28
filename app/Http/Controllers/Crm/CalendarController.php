<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A month of approved leads and deals closed as clients.
 *
 * Approved means the lead is qualified, booked, in proposal, or won.
 * A lost deal is closed, but it is not a client, so it stays off the calendar.
 * Leads are placed on the day they were captured. Won deals use the day they
 * were closed.
 */
final class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('lead.view') || $user->can('deal.view')), 403);

        $month = $this->month($request);
        $start = $month->startOfDay();
        $end = $month->endOfMonth()->endOfDay();

        /** @var list<array{date: string, kind: string, title: string, subtitle: string, href: string}> $events */
        $events = [];

        if ($user->can('lead.view')) {
            $leads = Lead::query()
                ->master()
                ->whereIn('status', array_map(
                    fn (LeadStatus $status): string => $status->value,
                    LeadStatus::approved(),
                ))
                ->whereBetween('created_at', [$start, $end])
                ->orderBy('created_at')
                ->get(['id', 'full_name', 'email', 'phone', 'status', 'created_at']);

            foreach ($leads as $lead) {
                $events[] = [
                    'date' => $lead->created_at->toDateString(),
                    'kind' => 'lead',
                    'title' => $lead->displayName(),
                    'subtitle' => $lead->status->label(),
                    'href' => '/leads/'.$lead->id,
                ];
            }
        }

        if ($user->can('deal.view')) {
            $deals = Deal::query()
                ->where('status', DealStatus::Won)
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('closed_at', [$start, $end])
                        ->orWhere(function ($query) use ($start, $end): void {
                            $query->whereNull('closed_at')->whereBetween('updated_at', [$start, $end]);
                        });
                })
                ->orderBy('closed_at')
                ->get(['id', 'title', 'closed_at', 'updated_at']);

            foreach ($deals as $deal) {
                $when = $deal->closed_at ?? $deal->updated_at;

                $events[] = [
                    'date' => $when->toDateString(),
                    'kind' => 'deal',
                    'title' => $deal->title,
                    'subtitle' => 'Closed client',
                    'href' => '/deals/'.$deal->id,
                ];
            }
        }

        return Inertia::render('crm/calendar/Index', [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'previous' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'today' => now()->toDateString(),
            'days' => $this->days($month, $events),
        ]);
    }

    private function month(Request $request): CarbonImmutable
    {
        $value = $request->string('month')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value.'-01');

            if ($parsed instanceof CarbonImmutable) {
                return $parsed->startOfMonth();
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @param  list<array{date: string, kind: string, title: string, subtitle: string, href: string}>  $events
     * @return list<array{date: string, day: int, in_month: bool, events: list<array{date: string, kind: string, title: string, subtitle: string, href: string}>}>
     */
    private function days(CarbonImmutable $month, array $events): array
    {
        $byDate = [];

        foreach ($events as $event) {
            $byDate[$event['date']][] = $event;
        }

        $cursor = $month->startOfWeek(CarbonImmutable::MONDAY);
        $last = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $days = [];

        while ($cursor->lte($last)) {
            $key = $cursor->toDateString();
            $days[] = [
                'date' => $key,
                'day' => $cursor->day,
                'in_month' => $cursor->month === $month->month,
                'events' => $byDate[$key] ?? [],
            ];
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
