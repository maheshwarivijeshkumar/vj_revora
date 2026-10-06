<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead verification (§18).
 *
 * Stored rather than computed on read, for the same reason the score band is:
 * the answer depends on a DNS lookup and on tunable thresholds, and
 * recomputing it per request would make the list slow and the history
 * unstable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('verification_status', 16)->default('unverified');
            $table->unsignedTinyInteger('verification_confidence')->nullable();
            // The findings that produced the status, so "not usable" can always
            // be explained to the rep looking at it (§59).
            $table->json('verification_findings')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Leads the workspace should actually work, newest first, is the
            // query this exists to serve.
            $table->index(['tenant_id', 'verification_status', 'created_at'], 'leads_verification_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_verification_index');
            $table->dropColumn([
                'verification_status',
                'verification_confidence',
                'verification_findings',
                'verified_at',
            ]);
        });
    }
};
