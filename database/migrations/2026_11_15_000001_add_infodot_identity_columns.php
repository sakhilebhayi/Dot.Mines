<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-52 section 6: the stable keys that link a Dot.Mines user and a Dot.Mines team to their InfoDot counterparts.
 *
 * Both columns are nullable (every existing row stays valid and unlinked) and unique (one InfoDot identity maps to at
 * most one Mines user, one InfoDot company to at most one Mines team; NULLs never collide on MySQL or SQLite). Additive
 * only: no existing column or row is touched, so it is safe on the shared MySQL and rolls back by dropping what it added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'infodot_user_uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('infodot_user_uuid', 36)->nullable()->unique('users_infodot_user_uuid_unique');
            });
        }

        if (! Schema::hasColumn('teams', 'infodot_team_uuid')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('infodot_team_uuid', 36)->nullable()->unique('teams_infodot_team_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'infodot_user_uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_infodot_user_uuid_unique');
                $table->dropColumn('infodot_user_uuid');
            });
        }

        if (Schema::hasColumn('teams', 'infodot_team_uuid')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropUnique('teams_infodot_team_uuid_unique');
                $table->dropColumn('infodot_team_uuid');
            });
        }
    }
};
