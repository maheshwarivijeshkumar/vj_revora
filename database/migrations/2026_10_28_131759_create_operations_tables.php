<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The work that starts once a lead is won: the client, their project,
 * estimates, invoices, and the people and materials around that job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('planning');
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->json('lines');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->json('lines');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->unique('estimate_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('crew_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('subcontractors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trade')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit')->default('each');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        Schema::create('change_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('schedule_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crew_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
        Schema::dropIfExists('change_orders');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('subcontractors');
        Schema::dropIfExists('crew_members');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
    }
};
