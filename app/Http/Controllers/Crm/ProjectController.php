<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Operations\Enums\ProjectStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::query()
            ->with('client')
            ->withCount('changeOrders')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Project $project): array => $this->row($project))
            ->all();

        return Inertia::render('crm/projects/Index', [
            'projects' => $projects,
            'clients' => $this->clients(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load(['client', 'changeOrders']);

        return Inertia::render('crm/projects/Show', [
            'project' => [
                ...$this->row($project),
                'description' => $project->description,
                'starts_on' => $project->starts_on?->toDateString(),
                'ends_on' => $project->ends_on?->toDateString(),
            ],
            'changeOrders' => $project->changeOrders->map(fn ($order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'title' => $order->title,
                'amount' => $order->amount,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_tone' => $order->status->tone(),
                'reason' => $order->reason,
            ])->all(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $project = Project::query()->create([
            ...$validated,
            'status' => ProjectStatus::Planning,
        ]);

        return to_route('projects.show', $project)->with('success', "{$project->name} has been opened.");
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        $project->fill($validated);
        $project->save();

        return back()->with('success', "{$project->name} has been updated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'client_id' => $project->client_id,
            'client' => $project->client?->name,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'status_tone' => $project->status->tone(),
            'change_orders_count' => $project->change_orders_count ?? $project->changeOrders()->count(),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function clients(): array
    {
        return Client::query()->orderBy('name')->limit(200)->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return array_map(
            fn (ProjectStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ProjectStatus::cases(),
        );
    }
}
