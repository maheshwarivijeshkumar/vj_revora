<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Tenders\Enums\TenderBuyerKind;
use App\Domain\Tenders\Enums\TenderTopic;
use App\Domain\Tenders\TenderPortal;
use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Procurement notices, web, software and IT first.
 *
 * Filtered and paginated in the database, like every other list.
 * Buyers are public, semi-government, private, or private industry.
 */
final class TenderController extends Controller
{
    private const PAGE_SIZES = [25, 50, 100];

    private const SORTABLE = ['closing_at', 'advertised_on', 'organization', 'first_seen_at'];

    /**
     * @var array<string, string>
     */
    private const SORTS = [
        'closing_at:asc' => 'Deadline, soonest',
        'closing_at:desc' => 'Deadline, latest',
        'advertised_on:desc' => 'Issued, newest',
        'advertised_on:asc' => 'Issued, oldest',
        'organization:asc' => 'Organization, A to Z',
        'organization:desc' => 'Organization, Z to A',
        'first_seen_at:desc' => 'Recently added',
        'first_seen_at:asc' => 'Oldest first',
    ];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'closing_at';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $sortKey = array_key_exists($sort.':'.$direction, self::SORTS)
            ? $sort.':'.$direction
            : 'closing_at:asc';
        [$sort, $direction] = explode(':', $sortKey);

        // Web, software and IT unless the user widens it: that is what the
        // page is for, and "all" is ~2,000 notices for furniture and fuel.
        [$topics, $topicMode] = $this->topics($request);
        $show = $request->string('show')->toString() === 'all' ? 'all' : 'open';
        $source = $request->string('source')->toString();
        $source = array_key_exists($source, TenderPortal::labels()) ? $source : '';
        $country = $request->string('country')->toString();
        $country = collect(TenderPortal::countries())->contains('value', $country) ? $country : '';
        $buyer = TenderBuyerKind::tryFrom($request->string('buyer')->toString());

        if ($country !== '' && $source !== '' && TenderPortal::country($source) !== $country) {
            $source = '';
        }

        $query = Tender::query()
            ->when($topics !== [], fn (Builder $q) => $q->whereIn('topic', $topics))
            ->when($show === 'open', fn (Builder $q) => $q->open())
            ->when($country !== '', fn (Builder $q) => $q->where('country', $country))
            ->when($buyer instanceof TenderBuyerKind, fn (Builder $q) => $q->where('buyer_kind', $buyer))
            ->when($source !== '', fn (Builder $q) => $q->where('source', $source));

        $term = $request->string('search')->trim()->toString();

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('buyer_reference', 'like', $like)
                    ->orWhere('organization', 'like', $like)
                    ->orWhere('department', 'like', $like)
                    ->orWhere('city', 'like', $like);
            });
        }

        $tenders = $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tender $tender): array => [
                'id' => $tender->id,
                'source' => $tender->source,
                'source_label' => $tender->sourceLabel(),
                'country' => $tender->country,
                'buyer_kind' => $tender->buyer_kind->value,
                'buyer_label' => $tender->buyer_kind->label(),
                'buyer_tone' => $tender->buyer_kind->tone(),
                'reference' => $tender->reference,
                'title' => $tender->title,
                'buyer_reference' => $tender->buyer_reference,
                'organization' => $tender->organization,
                'department' => $tender->department,
                'city' => $tender->city,
                'sector' => $tender->sector,
                'category' => $tender->procurement_category,
                'tender_type' => $tender->tender_type,
                'status' => $tender->status,
                'topic' => $tender->topic->value,
                'topic_label' => $tender->topic->label(),
                'topic_tone' => $tender->topic->tone(),
                'advertised_on' => $tender->advertised_on?->toDateString(),
                'closing_at' => $tender->closing_at?->toIso8601String(),
                'is_open' => $tender->isOpen(),
                'is_new' => $tender->first_seen_at->greaterThan(now()->subDay()),
                'url' => $tender->url,
            ]);

        return Inertia::render('crm/tenders/Index', [
            'tenders' => $tenders,
            'filters' => [
                'search' => $term,
                'topic' => $topics,
                'topic_mode' => $topicMode,
                'country' => $country,
                'buyer' => $buyer?->value ?? '',
                'source' => $source,
                'show' => $show,
                'sort' => $sort,
                'direction' => $direction,
                'sort_key' => $sortKey,
                'per_page' => $perPage,
            ],
            'topics' => TenderTopic::options(),
            'buyers' => TenderBuyerKind::options(),
            'sorts' => $this->sortOptions(),
            'countries' => TenderPortal::countries(),
            'sources' => TenderPortal::options(),
            'pageSizes' => self::PAGE_SIZES,
            'lastFetchedAt' => Tender::query()->max('last_seen_at'),
            'schedule' => '8:00 and 16:00 Pakistan time',
        ]);
    }

    /**
     * Starts a portal pull in the background. The scheduled run still reads
     * every portal; this only chooses what to refresh now.
     */
    public function sync(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('lead.update'), 403);

        $sources = array_values(array_filter(
            (array) $request->input('sources', []),
            fn (mixed $source): bool => is_string($source) && array_key_exists($source, TenderPortal::labels()),
        ));

        if ($sources === []) {
            return back()->with('error', 'Choose at least one portal to sync.');
        }

        $command = [PHP_BINARY, base_path('artisan'), 'tenders:fetch'];

        foreach ($sources as $source) {
            $command[] = '--source='.$source;
        }

        Process::path(base_path())->command($command)->start();

        $names = array_map(fn (string $source): string => TenderPortal::label($source), $sources);

        return back()->with('success', 'Sync started for '.implode(', ', $names).'. New notices show up when the pull finishes.');
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private function topics(Request $request): array
    {
        $input = $request->input('topic', ['web', 'software', 'it']);

        if ($input === 'all') {
            return [[], 'all'];
        }

        $topics = array_values(array_filter(
            (array) $input,
            fn (mixed $topic): bool => TenderTopic::tryFrom((string) $topic) !== null,
        ));

        $focused = ['it', 'software', 'web'];
        $current = $topics;
        sort($current);

        if ($current === $focused) {
            return [$topics, 'focused'];
        }

        if (count($topics) === 1) {
            return [$topics, $topics[0]];
        }

        if ($topics === []) {
            return [['web', 'software', 'it'], 'focused'];
        }

        return [$topics, 'focused'];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sortOptions(): array
    {
        $options = [];

        foreach (self::SORTS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
