<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public procurement notices from the Government of Pakistan (PPRA EPMS).
 *
 * Platform-wide rather than tenant-owned: a tender is a public notice, the
 * same for every workspace, so it is fetched once and read by all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenders', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            // The source's own tender number, e.g. TS0000016812E.
            $table->string('reference');
            $table->string('title', 1000);
            $table->text('description')->nullable();
            $table->string('buyer_reference')->nullable();
            $table->string('organization')->nullable();
            $table->string('department')->nullable();
            $table->string('city')->nullable();
            $table->string('sector')->nullable();
            $table->string('procurement_category')->nullable();
            $table->string('tender_type')->nullable();
            $table->string('method')->nullable();
            $table->string('nature')->nullable();
            $table->string('status')->nullable();
            // Issue date, and the bid submission deadline (Pakistan time,
            // stored in UTC like every other timestamp).
            $table->date('advertised_on')->nullable();
            $table->timestamp('closing_at')->nullable();
            $table->string('topic')->index();
            $table->boolean('is_it_related')->default(false);
            $table->string('url', 2048);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source', 'reference']);
            $table->index(['is_it_related', 'closing_at']);
            $table->index('advertised_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
