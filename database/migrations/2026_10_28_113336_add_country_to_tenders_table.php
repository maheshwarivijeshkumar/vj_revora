<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenders now come from more than one government. Country is stored on the
 * notice so the list can say which state published it without guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table): void {
            $table->string('country', 64)->nullable()->after('source');
        });

        DB::table('tenders')->where('source', 'ppra')->update(['country' => 'Pakistan']);
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table): void {
            $table->dropColumn('country');
        });
    }
};
