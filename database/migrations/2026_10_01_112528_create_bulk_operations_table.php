<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress for a bulk action (§113).
 *
 * A record rather than a job status, because §113 asks for progress and a
 * success/failure summary, and both have to survive the request that started
 * the work and the worker that finished it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity', 40);
            $table->string('action', 40);
            // The selection and the arguments, so a queued run does not depend
            // on a request that has already ended.
            $table->json('ids');
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('total');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // Why individual records were skipped, so a partial result is
            // explainable rather than just a smaller number than expected.
            $table->json('errors')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operations');
    }
};
