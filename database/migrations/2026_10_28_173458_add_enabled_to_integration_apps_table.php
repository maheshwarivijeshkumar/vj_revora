<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A network stays off until the workspace turns it on with its required keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_apps', function (Blueprint $table): void {
            $table->boolean('enabled')->default(false)->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('integration_apps', function (Blueprint $table): void {
            $table->dropColumn('enabled');
        });
    }
};
