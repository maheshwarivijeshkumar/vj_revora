<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App IDs and secrets a workspace enters on Settings → Integrations.
 * Secrets stay encrypted and are never sent back to the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_apps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('api_key')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_apps');
    }
};
