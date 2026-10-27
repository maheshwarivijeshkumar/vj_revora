<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to any CRM record (§21).
 *
 * Polymorphic like notes and tasks, for the same reason: four near-identical
 * tables is how one entity quietly ends up unable to hold a signed contract.
 *
 * The row records where the file is and what it was called when it arrived; the
 * stored name is generated, so nothing user-supplied reaches the filesystem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            // The public identifier. Download URLs carry this rather than the
            // auto-increment id, so a URL does not advertise how many files a
            // workspace holds.
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            // Which disk held it when it was written. Stored per row so moving
            // the default from local to s3 does not orphan everything already
            // uploaded.
            $table->string('disk', 32);
            $table->string('path');
            // What the person called it. Shown in the UI and used for the
            // download filename; never used to build the storage path.
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            // sha256 of the contents: lets the same file attached twice be
            // recognised, and proves a stored file has not changed under us.
            $table->string('checksum', 64);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Soft deleted, and the file stays on disk until the row is purged
            // — a restore that cannot return the file is not a restore.
            $table->softDeletes();

            // "The files on this record, newest first" is the only query the
            // panel makes.
            $table->index(['subject_type', 'subject_id', 'id'], 'documents_subject_index');
            // Leads with tenant_id so the tenant scope never drives a full scan.
            $table->index(['tenant_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
