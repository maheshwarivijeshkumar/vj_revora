<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans and the feature catalog.
 *
 * Runs before tenants, which carries a plan_id foreign key.
 *
 * Nothing here is mirrored in application code: adding a plan, a limit or a
 * feature is a data change, never a deploy (§8, §101.28).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // boolean = on/off capability, limit = capped quantity,
            // metered = consumed and billed by usage.
            $table->string('type')->default('boolean');
            $table->string('category')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('interval')->default('monthly');
            $table->decimal('price', 15, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_public', 'sort_order']);
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key')->index();
            // JSON so one column serves booleans, numeric limits and structured
            // grants without a schema change per feature type.
            $table->json('value')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('feature_catalog');
    }
};
