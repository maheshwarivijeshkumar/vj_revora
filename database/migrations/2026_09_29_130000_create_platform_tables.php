<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level operational tables: audit trail, teams, settings,
 * integration provider registry, feature flags and the dashboard widget
 * catalog (§54, §60, §94, §80).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Central with a nullable tenant_id, so platform-level and
        // tenant-level actions share one queryable trail (§54).
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('actor');
            $table->string('action')->index();
            $table->nullableMorphs('entity');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['tenant_id', 'action', 'created_at']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('timezone')->default('UTC');
            $table->json('business_hours')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('integration_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->boolean('is_enabled')->default(false);
            // Stored, never hard-coded in controllers, so a provider API
            // version bump is configuration rather than a code change (§76).
            $table->string('api_version')->nullable();
            $table->json('config')->nullable();
            $table->string('docs_url')->nullable();
            $table->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(0);
            $table->json('tenant_allowlist')->nullable();
            $table->timestamps();
        });

        // Catalog, not per-tenant rows: this is the registry the "+ Add Widget"
        // picker reads, and the reason dashboards are data-driven (§36, §80).
        Schema::create('dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('category')->index();
            $table->string('permission')->nullable();
            $table->boolean('configurable')->default(true);
            $table->boolean('refreshable')->default(true);
            $table->boolean('date_filter')->default(true);
            $table->json('default_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dashboards', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Null user_id = a shared dashboard for the whole tenant.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->string('role_key')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('dashboard_layouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('widget_key');
            $table->unsignedInteger('x')->default(0);
            $table->unsignedInteger('y')->default(0);
            $table->unsignedInteger('width')->default(3);
            $table->unsignedInteger('height')->default(2);
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['dashboard_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
        Schema::dropIfExists('dashboards');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('integration_providers');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('audit_logs');
    }
};
