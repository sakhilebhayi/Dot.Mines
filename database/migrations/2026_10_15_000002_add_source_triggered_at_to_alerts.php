<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `triggered_at` is when Dot.Mines ingested an alert; the provider's own
 * time for it (Bell's caution-code timestamp) was only in the dedupe key.
 * Keep it as `source_triggered_at` so integrators can build a true timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->timestamp('source_triggered_at')->nullable()->after('triggered_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropColumn('source_triggered_at');
        });
    }
};
