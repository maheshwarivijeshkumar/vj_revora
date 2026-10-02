<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhooks (§49).
 *
 * Kept portable for the PostgreSQL move (ADR-008): no MySQL-only column types,
 * and `json` rather than a vendor-specific document type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('url', 2048);
            // Stored in full, not hashed: signing a payload needs the secret
            // itself, and a subscriber has to be able to read it back to
            // configure their own verification.
            $table->string('secret', 128);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            // Consecutive, so a single bad day does not count towards a
            // disable once the endpoint recovers.
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            // The payload as it was sent, so a replay reproduces the original
            // rather than re-serialising a record that has since changed.
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            // Truncated when written. A subscriber returning a 200-page HTML
            // error must not be able to fill the table.
            $table->text('response_body')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            // Set when this delivery is a hand-triggered replay of another, so
            // a log reader can tell a retry from a re-send.
            $table->foreignId('replay_of_id')->nullable()->constrained('webhook_deliveries')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['webhook_endpoint_id', 'status']);
            $table->index(['tenant_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
