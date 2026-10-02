<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes users tenant-owned, and adds the profile fields the application needs.
 *
 * Separate from the base users migration because that one ships with the
 * starter kit and runs before `tenants` exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            $table->uuid()->after('tenant_id')->unique();
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('timezone')->default('UTC')->after('avatar_path');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->string('status')->default('active')->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->softDeletes();

            // Email is unique per tenant, not globally: the same person may
            // legitimately belong to two different customer workspaces.
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'status']);
        });

        // Drop the starter's global unique on email, now superseded by the
        // composite above.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn([
                'uuid', 'avatar_path', 'timezone', 'locale',
                'status', 'last_login_at', 'deleted_at',
            ]);
            $table->unique('email');
        });
    }
};
