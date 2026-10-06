<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of line a lead's number is (§17).
 *
 * Stored rather than parsed on demand: "can this lead be texted" decides which
 * channel reaches them, and parsing the number on every send would mean loading
 * Google's metadata on the hot path of every message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('phone_type', 20)->nullable();
            $table->string('phone_country', 2)->nullable();

            // "Every mobile lead in the UAE" is the segment this exists to
            // serve, and it is the one messaging will ask for constantly.
            $table->index(['tenant_id', 'phone_country', 'phone_type'], 'leads_phone_meta_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_phone_meta_index');
            $table->dropColumn(['phone_type', 'phone_country']);
        });
    }
};
