<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities, tasks and notes (§21).
 *
 * All three hang off any entity polymorphically, so one implementation serves
 * leads, contacts, companies and deals. Four near-identical sets of tables is
 * how one of them ends up missing a feature the others have.
 *
 * `lead_events` stays as it is: that records what the *system* did to a lead
 * (captured, scored, verified) and is append-only evidence. These record what
 * *people* did, across every entity, and a person can correct their own note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('type', 32);
            $table->string('summary');
            $table->text('body')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // When it happened, not when it was typed up: a call logged on
            // Monday for a conversation on Friday belongs on Friday.
            $table->timestamp('occurred_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // "What happened to this record, newest first" is the only query
            // the timeline makes.
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'activities_subject_index');
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Nullable: a task can stand alone ("call the new starters"), and
            // forcing a subject would push people to attach it to something
            // arbitrary.
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // "My open tasks, soonest first" is the query that drives the day.
            $table->index(['tenant_id', 'assigned_to', 'status', 'due_at'], 'tasks_mine_index');
            $table->index(['subject_type', 'subject_id'], 'tasks_subject_index');
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->text('body');
            // One note can be held at the top of the record — the one thing
            // somebody picking this account up needs to read first.
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id', 'is_pinned'], 'notes_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('activities');
    }
};
