<?php

declare(strict_types=1);

use App\Domain\Tenders\Services\BuyerKindClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retags buyers after the classifier stopped treating a ministry or a state
 * factory as private industry. The previous tags were derived, so rolling
 * back would only restore that mistake.
 */
return new class extends Migration
{
    public function up(): void
    {
        $classifier = app(BuyerKindClassifier::class);

        DB::table('tenders')->orderBy('id')->chunkById(200, function ($rows) use ($classifier): void {
            foreach ($rows as $row) {
                DB::table('tenders')->where('id', $row->id)->update([
                    'buyer_kind' => $classifier->classify($row->organization, $row->department)->value,
                ]);
            }
        });
    }

    public function down(): void
    {
        //
    }
};
