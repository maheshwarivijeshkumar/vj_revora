<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies, contacts, pipelines and deals (§21, §22).
 *
 * Schema stays portable (no MySQL-only constructs) so the PostgreSQL move in
 * ADR-008 remains a configuration change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('industry')->nullable();
            $table->string('size')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_system')->nullable();
            $table->string('external_record_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
            // Matching on email domain is how a contact gets attached to the
            // right company without anyone typing the company name twice.
            $table->index(['tenant_id', 'domain']);
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('email_normalized')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_normalized', 32)->nullable();
            $table->string('job_title')->nullable();
            $table->string('country', 2)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // The lead this contact was converted from, so the acquisition
            // story survives conversion (§85).
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_system')->nullable();
            $table->string('external_record_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'email_normalized']);
            $table->index(['tenant_id', 'phone_normalized']);
            $table->index(['tenant_id', 'full_name']);
        });

        // Many-to-many: a person can hold roles at more than one company, and
        // a company obviously has many contacts.
        Schema::create('contact_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['contact_id', 'company_id']);
        });

        Schema::create('pipelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            // A workspace may run separate boards for deals and for leads
            // (§22), so a pipeline declares what it moves.
            $table->string('entity_type')->default('deal')->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            // Drives weighted forecasting. Stored per stage so a workspace can
            // tune it against its own conversion history.
            $table->unsignedTinyInteger('probability')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            // Terminal stages. Kept as flags rather than inferred from
            // position, because "Won" is not always the last column.
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->string('color')->nullable();
            // Fields a deal must have before it may enter this stage (§22).
            $table->json('required_fields')->nullable();
            $table->timestamps();

            $table->unique(['pipeline_id', 'key']);
            $table->index(['tenant_id', 'pipeline_id', 'sort_order']);
        });

        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('value', 15, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->cascadeOnDelete();
            // Copied from the stage on entry, then editable: a rep who knows
            // this particular deal is shakier than its stage suggests must be
            // able to say so without moving it backwards.
            $table->unsignedTinyInteger('probability')->default(0);
            $table->string('status')->default('open')->index();

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason')->nullable();

            // Card order within a stage. Integer with renumbering rather than
            // a fractional index: a stage holds tens of cards, not millions,
            // and integers stay readable in the database.
            $table->unsignedInteger('position')->default(0);

            $table->string('external_system')->nullable();
            $table->string('external_record_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'pipeline_id', 'pipeline_stage_id', 'position'], 'deals_board_index');
            $table->index(['tenant_id', 'owner_id', 'status']);
            $table->index(['tenant_id', 'expected_close_date']);
        });

        // An append-only record of movement between stages, which is what
        // makes stage-duration and conversion reporting possible at all.
        Schema::create('deal_stage_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            // How long the deal sat in the stage it just left.
            $table->unsignedInteger('seconds_in_previous_stage')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'deal_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_stage_changes');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('contact_company');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
