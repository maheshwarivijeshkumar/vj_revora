<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Actions;

use App\Domain\Tenders\Services\PpraTenderSource;
use App\Domain\Tenders\Services\TenderClassifier;
use App\Models\Tender;
use Illuminate\Support\Carbon;

/**
 * Fetches every active PPRA tender and stores it, classified.
 *
 * Upserts on the tender number, so a run twice a day adds what is new and
 * refreshes what changed (a corrigendum moving the closing date, a status
 * becoming Cancelled) without duplicating anything.
 */
final readonly class ImportTenders
{
    public function __construct(
        private PpraTenderSource $source,
        private TenderClassifier $classifier,
    ) {}

    /**
     * @return array{fetched: int, created: int, updated: int, it_related: int}
     */
    public function handle(): array
    {
        $counts = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'it_related' => 0];
        $now = Carbon::now();

        foreach ($this->source->fetchAll() as $notice) {
            $counts['fetched']++;
            $topic = $this->classifier->classify($notice);

            $tender = Tender::query()->firstOrNew([
                'source' => PpraTenderSource::SOURCE,
                'reference' => $notice->reference,
            ]);

            $tender->fill([
                'title' => mb_substr($notice->title, 0, 1000),
                'description' => $notice->description,
                'buyer_reference' => $notice->buyerReference,
                'organization' => $notice->organization,
                'department' => $notice->department,
                'city' => $notice->city,
                'sector' => $notice->sector,
                'procurement_category' => $notice->procurementCategory,
                'tender_type' => $notice->tenderType,
                'method' => $notice->method,
                'nature' => $notice->nature,
                'status' => $notice->status,
                'advertised_on' => $notice->advertisedOn,
                'closing_at' => $notice->closingAt,
                'topic' => $topic,
                'is_it_related' => $topic->isItRelated(),
                'url' => $notice->url,
                'last_seen_at' => $now,
            ]);

            $tender->first_seen_at ??= $now;
            $isNew = ! $tender->exists;
            $tender->save();

            $isNew ? $counts['created']++ : $counts['updated']++;

            if ($topic->isItRelated()) {
                $counts['it_related']++;
            }
        }

        return $counts;
    }
}
