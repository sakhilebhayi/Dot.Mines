<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid architecture Slice 1: the incremental sync backbone.
 *
 * `sync_versions` is a monotonic global sequence -- each row's auto-increment
 * id IS a version number, handed out one insert at a time, which is atomic
 * and portable across sqlite/mysql/pgsql. `sync_tombstones` records deletions
 * so clients can evict cached rows. The `sync_version` columns stamp each
 * synced entity with the sequence value of its last change, letting
 * GET /api/v1/sync return only rows changed since a client's cursor.
 * Purely additive: no existing column is modified.
 */
return new class extends Migration
{
    private const SYNCED_TABLES = ['machines', 'notifications', 'mine_areas', 'production_records'];

    public function up(): void
    {
        Schema::create('sync_versions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->index();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('sync_version')->index();
            $table->timestamp('created_at')->nullable();
        });

        foreach (self::SYNCED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('sync_version')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::SYNCED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('sync_version');
            });
        }

        Schema::dropIfExists('sync_tombstones');
        Schema::dropIfExists('sync_versions');
    }
};
