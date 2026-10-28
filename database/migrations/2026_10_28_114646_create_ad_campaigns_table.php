<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name');
            $table->string('status', 64)->nullable();
            $table->string('objective', 64)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
