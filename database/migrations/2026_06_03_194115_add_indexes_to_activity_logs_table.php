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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Most queries filter by team_id + order by created_at
            $table->index(['team_id', 'created_at'], 'idx_activity_logs_team_time');
            // Secondary: filter by user_id for per-user audit views
            $table->index('user_id', 'idx_activity_logs_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_logs_team_time');
            $table->dropIndex('idx_activity_logs_user');
        });
    }
};
