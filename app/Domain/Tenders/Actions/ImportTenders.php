<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Actions;

use App\Domain\Tenders\DataObjects\TenderNotice;
use App\Domain\Tenders\Services\BuyerKindClassifier;
use App\Domain\Tenders\Services\TenderClassifier;
use App\Models\Tender;
use Illuminate\Support\Carbon;

/**
 * Stores notices from one feed, classified by specialization and buyer type.
 *
 * Upserts on the portal plus its own reference, so a later run refreshes a
 * closing date or a cancellation without creating a second row.
 */
final readonly class ImportTenders
{
    public function __construct(
        private TenderClassifier $classifier,
        private BuyerKindClassifier $buyers,
    ) {}

    /**
     * @param  iterable<TenderNotice>  $notices
     * @return array{fetched: int, created: int, updated: int, it_related: int}
     */
    public function handle(iterable $notices): array
    {
        $counts = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'it_related' => 0];
        $now = Carbon::now();

        foreach ($notices as $notice) {
            $counts['fetched']++;
            $topic = $this->classifier->classify($notice);

            $tender = Tender::query()->firstOrNew([
                'source' => $notice->source,
                'reference' => $notice->reference,
            ]);

            $tender->fill([
                'country' => $notice->country,
                'buyer_kind' => $this->buyers->classify($notice->organization, $notice->department),
                'title' => mb_substr($notice->title, 0, 1000),
                'description' => $this->clip($notice->description, 20000),
                'buyer_reference' => $this->clip($notice->buyerReference),
                'organization' => $this->clip($notice->organization),
                'department' => $this->clip($notice->department),
                'city' => $this->clip($notice->city),
                'sector' => $this->clip($notice->sector),
                'procurement_category' => $this->clip($notice->procurementCategory),
                'tender_type' => $this->clip($notice->tenderType),
                'method' => $this->clip($notice->method),
                'nature' => $this->clip($notice->nature),
                'status' => $this->clip($notice->status),
                'advertised_on' => $notice->advertisedOn,
                'closing_at' => $notice->closingAt,
                'topic' => $topic,
                'is_it_related' => $topic->isItRelated(),
                'url' => mb_substr($notice->url, 0, 2048),
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

    private function clip(?string $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, $limit);
    }
}
