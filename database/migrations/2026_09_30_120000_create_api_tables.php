<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API keys and request logging (§48, §51, §74).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Only a prefix and a hash are stored. §48 is explicit that a
            // secret is never returned after creation, so it has to be
            // genuinely unrecoverable rather than merely hidden.
            $table->string('prefix', 16)->unique();
            $table->string('hash');

            $table->json('scopes');
            // Per-key override of the workspace limit (§51).
            $table->unsignedInteger('rate_limit')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'revoked_at']);
        });

        // Append-only. Backs the API usage figures in §74 and the request log
        // the developer portal shows (§50).
        Schema::create('api_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
        Schema::dropIfExists('api_keys');
    }
};
