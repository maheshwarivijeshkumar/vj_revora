<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lead engine (§17–§23, §85).
 *
 * Schema stays portable (no MySQL-only constructs) so the PostgreSQL move in
 * ADR-008 remains a configuration change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            // Distinguishes an authorized API connection from an import or an
            // unsupported origin, which the UI must show plainly (§2).
            $table->string('type')->index();
            $table->string('provider')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // --- Common schema (§17). Provider-specific fields go to metadata.
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('company_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('website')->nullable();

            // Normalised in PHP rather than as generated columns: lowercasing
            // an email is expressible in SQL, but E.164 phone normalisation is
            // not, and splitting the two across layers would be worse than
            // doing both in one place. See Lead::normaliseIdentifiers().
            $table->string('email_normalized')->nullable();
            $table->string('phone_normalized', 32)->nullable();

            // --- Lifecycle (§85)
            $table->string('status')->default('new')->index();
            $table->unsignedSmallInteger('score')->default(0);
            // Stored, not derived: thresholds are tenant-configurable (§119),
            // and recomputing on read would silently rewrite history when a
            // tenant retunes its bands.
            $table->string('score_band')->default('low');

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();

            // --- Attribution (§34)
            $table->json('utm')->nullable();
            $table->string('landing_page', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->json('first_touch')->nullable();
            $table->json('last_touch')->nullable();

            // --- External systems (§81), for the keep-your-CRM mode
            $table->string('external_system')->nullable();
            $table->string('external_record_type')->nullable();
            $table->string('external_record_id')->nullable();

            // --- Consent (§88)
            $table->boolean('consent')->default(false);
            $table->string('consent_source')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('privacy_policy_version')->nullable();

            $table->json('metadata')->nullable();

            // Duplicates become tombstones pointing at the master rather than
            // being deleted: §18 forbids silently destroying data.
            $table->foreignId('merged_into_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Every composite index leads with tenant_id: without it, scoping
            // turns each list query into a full table scan once the table is
            // shared by many workspaces.
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'owner_id', 'next_follow_up_at']);
            $table->index(['tenant_id', 'score']);
            $table->index(['tenant_id', 'email_normalized']);
            $table->index(['tenant_id', 'phone_normalized']);
            $table->index(['tenant_id', 'lead_source_id']);
        });

        // One workspace cannot hold the same external record twice. This is
        // what makes two-way CRM sync safe (§81).
        Schema::table('leads', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'external_system', 'external_record_id'],
                'leads_external_unique',
            );
        });

        Schema::create('lead_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('provider')->nullable();
            // Uniquely indexed per tenant so a replayed webhook is a no-op
            // rather than a duplicate event (§58).
            $table->string('provider_event_id')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'occurred_at']);
            $table->unique(['tenant_id', 'provider', 'provider_event_id'], 'lead_events_provider_unique');
        });

        Schema::create('lead_score_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            // { field, operator, value } — evaluated by LeadScorer.
            $table->json('condition');
            $table->smallInteger('points');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::create('lead_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score');
            $table->string('band');
            $table->string('method');
            // The user-facing explanation (§19). Never model chain-of-thought,
            // which §20 forbids storing or exposing.
            $table->json('reasons');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'computed_at']);
        });

        Schema::create('lead_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('strategy');
            $table->string('reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'assigned_at']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('lead_merges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('merged_lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('matched_on');
            // Exactly what was combined, so a merge can be explained and, if
            // necessary, reversed by hand.
            $table->json('diff')->nullable();
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at');
            $table->timestamps();

            $table->index(['tenant_id', 'master_lead_id']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // Polymorphic so one tagging implementation serves leads, contacts,
        // companies and deals (§21).
        Schema::create('taggables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('lead_merges');
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('lead_scores');
        Schema::dropIfExists('lead_score_rules');
        Schema::dropIfExists('lead_events');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_sources');
    }
};
