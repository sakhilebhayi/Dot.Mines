<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operators and operator-machine assignments join the incremental sync API
 * (scopes `operators` and `operator_assignments`), so a control-room
 * platform can mirror who may operate what. Same shape as the original
 * sync infrastructure: a nullable indexed version column, backfilled with
 * one fresh sequence value per table so the first pull returns every row.
 */
return new class extends Migration
{
    private const TABLES = ['operators', 'operator_machine_assignments'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('sync_version')->nullable()->index();
            });

            $version = DB::table('sync_versions')->insertGetId(['created_at' => now()]);
            DB::table($tableName)->whereNull('sync_version')->update(['sync_version' => $version]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('sync_version');
            });
        }
    }
};
