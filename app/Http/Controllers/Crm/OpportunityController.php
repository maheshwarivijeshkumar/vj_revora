<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Opportunities\Actions\ConvertOpportunityToLead;
use App\Domain\Opportunities\Actions\DraftProposal;
use App\Domain\Opportunities\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Models\OpportunityMatch;
use App\Models\ServiceProfile;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Job posts that fit what the workspace sells.
 *
 * Government tenders stay on their own page. A proposal is drafted here
 * and the person applies on the site that published the job.
 */
final class OpportunityController extends Controller
{
    private const PAGE_SIZES = [25, 50, 100];

    public function index(Request $request): Response
    {
        $perPage = in_array($request->integer('per_page', 25), self::PAGE_SIZES, true) ? $request->integer('per_page', 25) : 25;
        $source = $request->string('source')->toString();
        $status = $request->string('status')->toString() ?: 'open';
        $minFit = $request->has('min_fit') ? max(0, min(100, $request->integer('min_fit'))) : OpportunityMatch::HIGH_FIT;
        $profile = $request->string('profile')->toString();
        $term = $request->string('search')->trim()->toString();

        $source = array_key_exists($source, JobPost::SOURCES) ? $source : '';

        $query = OpportunityMatch::query()
            ->with('profile:id,name,kind')
            ->where('opportunity_type', OpportunityMatch::TYPE_JOB_POST)
            ->where('fit_score', '>=', $minFit)
            ->when($status === 'open', fn (Builder $q) => $q->whereIn('status', ['new', 'shortlisted', 'drafted']))
            ->when($status !== 'open' && $status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->when($profile !== '', fn (Builder $q) => $q->whereHas('profile', fn (Builder $p) => $p->where('uuid', $profile)))
            ->when($source !== '', fn (Builder $q) => $q->whereIn('opportunity_id', JobPost::query()->select('id')->where('source', $source)))
            ->when($term !== '', function (Builder $q) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->whereIn('opportunity_id', JobPost::query()->select('id')->where(fn (Builder $job) => $job
                    ->where('title', 'like', $like)
                    ->orWhere('company', 'like', $like)));
            });

        $matches = $query->orderByDesc('fit_score')->orderByDesc('id')->paginate($perPage)->withQueryString();

        // Two lookups for the page rather than one per row.
        $tenders = Tender::query()->whereIn('id', $matches->getCollection()->where('opportunity_type', 'tender')->pluck('opportunity_id'))->get()->keyBy('id');
        $jobs = JobPost::query()->whereIn('id', $matches->getCollection()->where('opportunity_type', 'job_post')->pluck('opportunity_id'))->get()->keyBy('id');

        return Inertia::render('crm/opportunities/Index', [
            'matches' => $matches->through(fn (OpportunityMatch $m): array => $this->row(
                $m,
                $m->opportunity_type === OpportunityMatch::TYPE_TENDER ? $tenders->get($m->opportunity_id) : $jobs->get($m->opportunity_id),
            )),
            'filters' => [
                'search' => $term,
                'source' => $source,
                'status' => $status,
                'min_fit' => $minFit,
                'profile' => $profile,
                'per_page' => $perPage,
            ],
            'sources' => array_map(
                fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
                array_keys(JobPost::SOURCES),
                JobPost::SOURCES,
            ),
            'resumes' => ServiceProfile::query()->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (ServiceProfile $profile): array => [
                    'id' => $profile->uuid,
                    'name' => $profile->name,
                    'kind' => $profile->kind,
                    'about' => $profile->about ?? '',
                ])->all(),
            'canEditResume' => (bool) $request->user()?->can('settings.manage'),
            'statuses' => OpportunityStatus::options(),
            'profiles' => ServiceProfile::query()->orderByDesc('is_default')->get(['uuid', 'name'])->map(fn (ServiceProfile $p): array => ['value' => $p->uuid, 'label' => $p->name])->all(),
            'counts' => [
                'job_post' => OpportunityMatch::query()
                    ->where('opportunity_type', OpportunityMatch::TYPE_JOB_POST)
                    ->where('fit_score', '>=', OpportunityMatch::HIGH_FIT)
                    ->whereIn('status', ['new', 'shortlisted', 'drafted'])
                    ->count(),
            ],
            'hasProfiles' => ServiceProfile::query()->exists(),
            'highFit' => OpportunityMatch::HIGH_FIT,
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('lead.update'), 403);

        $sources = array_values(array_filter(
            (array) $request->input('sources', []),
            fn (mixed $source): bool => is_string($source) && array_key_exists($source, JobPost::SOURCES),
        ));

        if ($sources === []) {
            return back()->with('error', 'Choose at least one source to sync.');
        }

        $command = [PHP_BINARY, base_path('artisan'), 'opportunities:fetch'];

        foreach ($sources as $source) {
            $command[] = '--source='.$source;
        }

        Process::path(base_path())->command($command)->start();

        $names = array_map(fn (string $source): string => JobPost::SOURCES[$source], $sources);

        return back()->with('success', 'Sync started for '.implode(', ', $names).'. New jobs show up when the pull finishes. Apply on each site yourself.');
    }

    public function resume(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        $validated = $request->validate([
            'profile' => ['required', 'uuid'],
            'about' => ['required', 'string', 'max:30000'],
        ]);

        $profile = ServiceProfile::query()->where('uuid', $validated['profile'])->firstOrFail();
        $profile->forceFill(['about' => $validated['about']])->save();

        return back()->with('success', "Resume saved for {$profile->name}. New drafts use this text.");
    }

    public function update(Request $request, OpportunityMatch $match): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(OpportunityStatus::class)],
            'draft' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $match->fill($validated);
        $match->updated_by = $request->user()?->id;
        $match->save();

        return back()->with('success', isset($validated['status'])
            ? 'Marked as '.mb_strtolower(OpportunityStatus::from($validated['status'])->label()).'.'
            : 'Saved.');
    }

    public function draft(OpportunityMatch $match, DraftProposal $draft): RedirectResponse
    {
        try {
            $draft->handle($match);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $match->drafted_by === 'template'
            ? 'Draft ready (from a template: add an AI key in Settings → Setup for tailored drafts). Review it before sending.'
            : 'Draft ready. Review and edit it before you send it.');
    }

    public function convert(OpportunityMatch $match, ConvertOpportunityToLead $convert): RedirectResponse
    {
        $lead = $convert->handle($match);

        return to_route('leads.show', $lead)->with('success', 'Added to your leads.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(OpportunityMatch $match, Tender|JobPost|null $opportunity): array
    {
        $base = [
            'id' => $match->uuid,
            'type' => $match->opportunity_type,
            'fit' => $match->fit_score,
            'services' => $match->fit_details['services'] ?? [],
            'reasons' => $match->fit_details['reasons'] ?? [],
            'status' => $match->status->value,
            'status_label' => $match->status->label(),
            'status_tone' => $match->status->tone(),
            'profile' => $match->profile?->name,
            'draft' => $match->draft,
            'drafted_by' => $match->drafted_by,
            'notes' => $match->notes,
            'lead_id' => $match->lead_id,
        ];

        if ($opportunity instanceof Tender) {
            return [
                ...$base,
                'source' => $opportunity->sourceLabel(),
                'title' => $opportunity->title,
                'who' => $opportunity->department ?? $opportunity->organization,
                'where' => $opportunity->city,
                'reference' => $opportunity->reference,
                'budget' => null,
                'posted_at' => $opportunity->advertised_on?->toDateString(),
                'closing_at' => $opportunity->closing_at?->toIso8601String(),
                'url' => $opportunity->url,
                'summary' => $opportunity->procurement_category.' · '.$opportunity->sector,
            ];
        }

        if ($opportunity instanceof JobPost) {
            $signals = $opportunity->signals ?? [];

            return [
                ...$base,
                'source' => $opportunity->sourceLabel(),
                'title' => $opportunity->title,
                'who' => $opportunity->company ?? ($opportunity->source === 'upwork' ? 'Upwork client' : null),
                'where' => $opportunity->location,
                'reference' => null,
                'budget' => $opportunity->budgetLabel() ?? ($signals['salary_text'] ?? null),
                'posted_at' => $opportunity->posted_at?->toIso8601String(),
                'closing_at' => null,
                'url' => $opportunity->url,
                'summary' => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $opportunity->description))) ?? ''), 0, 280),
            ];
        }

        return [...$base, 'source' => 'Removed', 'title' => 'No longer available', 'who' => null, 'where' => null, 'reference' => null, 'budget' => null, 'posted_at' => null, 'closing_at' => null, 'url' => null, 'summary' => null];
    }
}
