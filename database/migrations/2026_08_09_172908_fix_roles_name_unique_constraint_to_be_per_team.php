<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * roles.name was created with a single-column unique constraint, so only
     * the first team in the database could ever have a role named "admin"
     * (or fleet_manager/operator/viewer) -- every other team's provisioning
     * would fail with a unique constraint violation. Replace it with a
     * (team_id, name) composite unique, matching the permissions table and
     * how the app actually queries roles (always scoped by team_id).
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasIndex('roles', 'roles_name_unique')) {
                $table->dropUnique('roles_name_unique');
            }

            // Composite unique may already exist from 2026_06_05_103931.
            if (! Schema::hasIndex('roles', 'roles_team_id_name_unique')) {
                $table->unique(['team_id', 'name']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'name']);
            $table->unique('name');
        });
    }
};
