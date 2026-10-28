<?php

declare(strict_types=1);

use App\Domain\Tenders\Services\BuyerKindClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retags buyers using the department as well as the organization. A supply
 * company listed under a ministry was being stored as the ministry.
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
