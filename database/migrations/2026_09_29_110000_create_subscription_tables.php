<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions, billing records and usage metering (§8, §9).
 *
 * Runs after tenants and plans, both of which are referenced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->index();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable()->index();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancels_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            // Set when a downgrade is scheduled for period end rather than
            // applied immediately, so entitlements stay at the higher tier
            // until the period actually rolls over.
            $table->foreignId('scheduled_plan_id')->nullable()->constrained('plans');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subscription_id', 'occurred_at']);
        });

        Schema::create('billing_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_customer_id');
            $table->json('billing_details')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_customer_id']);
        });

        Schema::create('billing_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 4);
            $table->string('currency', 3);
            $table->string('status')->index();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('invoice_url')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('usage_meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('meter_key');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedBigInteger('used')->default(0);
            $table->unsignedBigInteger('limit')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('overage_allowed')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'meter_key', 'period_start']);
        });

        Schema::create('usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('meter_key');
            $table->unsignedBigInteger('quantity')->default(1);
            $table->nullableMorphs('source');
            // Unique per tenant: a retried job must never double-count usage.
            $table->string('idempotency_key')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'meter_key', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('usage_meters');
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('billing_customers');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
