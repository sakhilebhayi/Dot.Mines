<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            // Add credentials column if it doesn't exist
            if (! Schema::hasColumn('integrations', 'credentials')) {
                $table->json('credentials')->nullable()->after('api_secret');
            }

            // Add last_sync_status column if it doesn't exist
            if (! Schema::hasColumn('integrations', 'last_sync_status')) {
                $table->string('last_sync_status')->nullable()->default('pending')->after('last_sync_at');
            }

            // Update status default if needed
            $table->string('status')->default('disconnected')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['credentials', 'last_sync_status']);
        });
    }
};
