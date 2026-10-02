<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enquiries from the public contact form.
 *
 * Central, not tenant-scoped: these arrive before any workspace exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('email');
            $table->string('company')->nullable();
            $table->string('topic')->index();
            $table->text('message');
            $table->string('status')->default('new')->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_enquiries');
    }
};
