<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the workspace sells, and the work it could win.
 *
 * Service profiles describe each business that sells (an agency, a
 * freelancer) so tenders and job posts can be scored against them. Job posts
 * are public, like tenders, so they are stored once for everyone; whether one
 * fits, and what was done about it, is per workspace (opportunity_matches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // agency | freelancer: decides "we" or "I" in drafted proposals.
            $table->string('kind');
            $table->string('tagline')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            // Who they are, experience, and (for a freelancer) the resume.
            $table->longText('about')->nullable();
            // [{name, summary, keywords: []}]
            $table->json('services');
            $table->json('skills')->nullable();
            // [{name, summary, url}]
            $table->json('portfolio')->nullable();
            $table->json('markets')->nullable();
            $table->text('pricing')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        Schema::create('job_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->string('title', 500);
            $table->string('company')->nullable();
            $table->longText('description')->nullable();
            $table->string('url', 2048);
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(true);
            $table->string('job_type')->nullable();
            $table->json('tags')->nullable();
            // fixed | hourly | salary
            $table->string('budget_type')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            // Quality signals the source offers (Upwork client spend, hires…).
            $table->json('signals')->nullable();
            // Normalised title + company, to fold the same post seen on
            // several boards into one.
            $table->string('fingerprint', 64)->index();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('posted_at');
        });

        Schema::create('opportunity_matches', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // tender | job_post
            $table->string('opportunity_type');
            $table->unsignedBigInteger('opportunity_id');
            $table->foreignId('service_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('fit_score');
            // {services: [...], keywords: [...], reasons: [...]}
            $table->json('fit_details')->nullable();
            // new, shortlisted, drafted, applied, won, lost, dismissed
            $table->string('status')->default('new')->index();
            $table->longText('draft')->nullable();
            $table->string('drafted_by')->nullable();
            $table->timestamp('drafted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'opportunity_type', 'opportunity_id'], 'opportunity_matches_unique');
            $table->index(['tenant_id', 'status', 'fit_score']);
        });

        Schema::create('upwork_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            // Access + refresh token, encrypted by the model cast (§11).
            $table->text('credentials');
            $table->string('organization_id')->nullable();
            $table->string('status')->default('connected');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upwork_accounts');
        Schema::dropIfExists('opportunity_matches');
        Schema::dropIfExists('job_posts');
        Schema::dropIfExists('service_profiles');
    }
};
