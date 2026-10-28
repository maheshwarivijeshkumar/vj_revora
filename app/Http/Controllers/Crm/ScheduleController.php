<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CrewMember;
use App\Models\Project;
use App\Models\ScheduleEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visits and blocks of work, the "today's schedule" list from the job board.
 */
final class ScheduleController extends Controller
{
    public function index(): Response
    {
        $entries = ScheduleEntry::query()
            ->with(['client', 'project', 'crewMember'])
            ->where('starts_at', '>=', now()->startOfDay()->subDay())
            ->orderBy('starts_at')
            ->limit(100)
            ->get()
            ->map(fn (ScheduleEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'starts_at' => $entry->starts_at->toIso8601String(),
                'ends_at' => $entry->ends_at?->toIso8601String(),
                'location' => $entry->location,
                'notes' => $entry->notes,
                'client' => $entry->client?->name,
                'project' => $entry->project?->name,
                'crew' => $entry->crewMember?->name,
            ])
            ->all();

        return Inertia::render('crm/schedule/Index', [
            'entries' => $entries,
            'clients' => Client::query()->orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])->all(),
            'projects' => Project::query()->orderBy('name')->limit(200)->get(['id', 'name', 'client_id'])
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_id' => $project->client_id,
                ])->all(),
            'crew' => CrewMember::query()->where('status', 'active')->orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (CrewMember $member): array => ['id' => $member->id, 'name' => $member->name])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'crew_member_id' => ['nullable', Rule::exists('crew_members', 'id')->where('tenant_id', $tenantId)],
        ]);

        ScheduleEntry::query()->create($validated);

        return back()->with('success', 'The visit is on the schedule.');
    }

    public function destroy(ScheduleEntry $scheduleEntry): RedirectResponse
    {
        $scheduleEntry->delete();

        return back()->with('success', 'The visit has been removed.');
    }
}
