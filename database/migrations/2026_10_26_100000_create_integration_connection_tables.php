<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connected lead-ad accounts and their sync history (§10, §57).
 *
 * One row per provider asset a workspace has authorized: a Facebook Page (which
 * also carries its Instagram lead ads) or a LinkedIn ad account. Tokens are
 * encrypted by the model cast, never stored or logged in the clear (§11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            // The provider's own id for the asset: a Page id, or a sponsored
            // account URN.
            $table->string('account_id');
            $table->string('account_name');
            $table->string('status')->default('connected')->index();
            $table->text('access_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'account_id']);
        });

        Schema::create('integration_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_connection_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->string('status');
            $table->unsignedInteger('leads_fetched')->default(0);
            $table->unsignedInteger('leads_created')->default(0);
            $table->unsignedInteger('leads_merged')->default(0);
            $table->unsignedInteger('leads_skipped')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'integration_connection_id', 'started_at'], 'sync_runs_connection_started');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('integration_connections');
    }
};
