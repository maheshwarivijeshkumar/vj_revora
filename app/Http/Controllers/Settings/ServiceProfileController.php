<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Opportunities\Actions\InstallPlaybook;
use App\Domain\Opportunities\Actions\MatchOpportunities;
use App\Http\Controllers\Controller;
use App\Models\ServiceProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the workspace sells: the profiles opportunities are matched against
 * and proposals are written from.
 */
final class ServiceProfileController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Services', [
            'profiles' => ServiceProfile::query()->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (ServiceProfile $p): array => [
                    'id' => $p->uuid,
                    'name' => $p->name,
                    'kind' => $p->kind,
                    'tagline' => $p->tagline,
                    'website' => $p->website,
                    'email' => $p->email,
                    'phone' => $p->phone,
                    'location' => $p->location,
                    'about' => $p->about,
                    'services' => array_map(fn (array $s): array => [
                        'name' => $s['name'],
                        'summary' => $s['summary'] ?? '',
                        'keywords' => implode(', ', $s['keywords'] ?? []),
                    ], $p->services),
                    'skills' => implode(', ', $p->skills ?? []),
                    'portfolio' => $p->portfolio ?? [],
                    'markets' => implode(', ', $p->markets ?? []),
                    'pricing' => $p->pricing,
                    'signature' => $p->signature,
                    'is_default' => $p->is_default,
                ])->all(),
        ]);
    }

    /**
     * Sets the workspace up from the two businesses' websites, with the full
     * template playbook, then scores what is already in.
     */
    public function install(InstallPlaybook $playbook, MatchOpportunities $match): RedirectResponse
    {
        $templates = $playbook->handle();
        $matches = $match->handle();

        return back()->with('success', "Profiles ready, {$templates} templates added and {$matches} opportunities matched.");
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in([ServiceProfile::KIND_AGENCY, ServiceProfile::KIND_FREELANCER])],
        ]);

        ServiceProfile::create([...$validated, 'services' => [], 'is_default' => ! ServiceProfile::query()->exists()]);

        return back()->with('success', 'Profile added. Fill in its services so opportunities can be matched to it.');
    }

    public function update(Request $request, ServiceProfile $profile, MatchOpportunities $match): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in([ServiceProfile::KIND_AGENCY, ServiceProfile::KIND_FREELANCER])],
            'tagline' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:30000'],
            'services' => ['present', 'array', 'max:30'],
            'services.*.name' => ['required', 'string', 'max:255'],
            'services.*.summary' => ['nullable', 'string', 'max:1000'],
            'services.*.keywords' => ['required', 'string', 'max:2000'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'portfolio' => ['present', 'array', 'max:30'],
            'portfolio.*.name' => ['required', 'string', 'max:255'],
            'portfolio.*.summary' => ['nullable', 'string', 'max:1000'],
            'portfolio.*.url' => ['nullable', 'url', 'max:500'],
            'markets' => ['nullable', 'string', 'max:500'],
            'pricing' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['required', 'boolean'],
        ], [
            'services.*.keywords.required' => 'Give each service at least one keyword: it is how matching jobs are found.',
        ]);

        $list = fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

        $profile->fill([
            ...Arr::except($validated, ['services', 'skills', 'markets', 'portfolio']),
            'services' => array_map(fn (array $s): array => [
                'name' => $s['name'],
                'summary' => (string) ($s['summary'] ?? ''),
                'keywords' => $list($s['keywords']),
            ], $validated['services']),
            'skills' => $list($validated['skills'] ?? null),
            'markets' => $list($validated['markets'] ?? null),
            'portfolio' => array_map(fn (array $p): array => [
                'name' => $p['name'],
                'summary' => (string) ($p['summary'] ?? ''),
                'url' => $p['url'] ?? null,
            ], $validated['portfolio']),
        ])->save();

        if ($profile->is_default) {
            ServiceProfile::query()->whereKeyNot($profile->id)->update(['is_default' => false]);
        }

        // New keywords change what fits, so untouched matches are re-scored.
        $match->handle();

        return back()->with('success', "{$profile->name} saved, and opportunities re-scored.");
    }

    public function destroy(ServiceProfile $profile): RedirectResponse
    {
        if (ServiceProfile::query()->count() === 1) {
            return back()->with('error', 'Keep at least one profile: opportunities are matched against it.');
        }

        $profile->delete();

        if ($profile->is_default) {
            ServiceProfile::query()->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
        }

        return back()->with('success', "{$profile->name} removed.");
    }
}
