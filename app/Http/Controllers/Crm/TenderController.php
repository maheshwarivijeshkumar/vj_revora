<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Tenders\Enums\TenderTopic;
use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Government of Pakistan tenders, web, software and IT first (§70).
 *
 * Filtered and paginated in the database, like every other list.
 */
final class TenderController extends Controller
{
    private const PAGE_SIZES = [25, 50, 100];

    private const SORTABLE = ['closing_at', 'advertised_on', 'organization', 'first_seen_at'];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'closing_at';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        // Web, software and IT unless the user widens it: that is what the
        // page is for, and "all" is ~2,000 notices for furniture and fuel.
        $topics = array_values(array_filter(
            (array) $request->input('topic', ['web', 'software', 'it']),
            fn (mixed $t): bool => TenderTopic::tryFrom((string) $t) !== null,
        ));
        $show = $request->string('show')->toString() === 'all' ? 'all' : 'open';

        $query = Tender::query()
            ->when($topics !== [], fn (Builder $q) => $q->whereIn('topic', $topics))
            ->when($show === 'open', fn (Builder $q) => $q->open());

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
                'show' => $show,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'topics' => TenderTopic::options(),
            'pageSizes' => self::PAGE_SIZES,
            'lastFetchedAt' => Tender::query()->max('last_seen_at'),
        ]);
    }
}
