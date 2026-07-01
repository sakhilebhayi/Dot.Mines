<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make integrations.api_key and api_secret nullable.
 *
 * Credentials are now stored encrypted in the `credentials` column.
 * The legacy api_key / api_secret columns are kept for backwards-compatibility
 * but are no longer required when creating or updating an integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->string('api_key')->nullable()->change();
            $table->string('api_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table): void {
            $table->string('api_key')->nullable(false)->change();
            $table->string('api_secret')->nullable(false)->change();
        });
    }
};
