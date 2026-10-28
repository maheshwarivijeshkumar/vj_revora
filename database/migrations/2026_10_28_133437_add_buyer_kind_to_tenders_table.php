<?php

declare(strict_types=1);

use App\Domain\Tenders\Services\BuyerKindClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each notice records who is buying, then existing rows are tagged from the
 * organization name. Government portals are not all ministries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table): void {
            $table->string('buyer_kind', 32)->default('public')->after('country')->index();
        });

        $classifier = app(BuyerKindClassifier::class);

        DB::table('tenders')->orderBy('id')->chunkById(200, function ($rows) use ($classifier): void {
            foreach ($rows as $row) {
                DB::table('tenders')->where('id', $row->id)->update([
                    'title' => $this->plain($row->title, 1000),
                    'organization' => $this->plain($row->organization),
                    'department' => $this->plain($row->department),
                    'city' => $this->plain($row->city),
                    'sector' => $this->plain($row->sector),
                    'buyer_kind' => $classifier->classify($row->organization, $row->department)->value,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table): void {
            $table->dropIndex(['buyer_kind']);
            $table->dropColumn('buyer_kind');
        });
    }

    private function plain(?string $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, $limit);
    }
};
