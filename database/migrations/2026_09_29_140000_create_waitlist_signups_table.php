<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-launch waitlist captured by the coming-soon page.
 *
 * Central, not tenant-scoped: these are prospects for the platform itself,
 * captured before any tenant exists.
 *
 * The UTM and referrer columns mirror the attribution model in §16/§34, so
 * these signups can be attributed the same way tenant leads are once the lead
 * engine lands — a launch campaign should not lose its source data just
 * because it ran before Phase 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_signups', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('email');
            $table->string('email_normalized')->unique();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('source')->default('coming_soon');
            $table->json('utm')->nullable();
            $table->string('landing_page')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            // §88: consent is recorded at capture, never inferred later.
            $table->boolean('consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_signups');
    }
};
