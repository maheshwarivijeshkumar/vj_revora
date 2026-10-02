<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central tenancy tables (docs/plan/02-DATA-MODEL.md §1).
 *
 * These carry no tenant_id — they are the platform's own records.
 *
 * Schema is kept deliberately portable (no MySQL-only constructs) so the
 * PostgreSQL move recorded in ADR-008 stays a configuration change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('provisioning')->index();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            // Records which provisioning steps have completed, so a failed run
            // resumes instead of restarting or being repaired by hand (§7).
            $table->json('provisioning_state')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
        });

        Schema::create('platform_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_users');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
