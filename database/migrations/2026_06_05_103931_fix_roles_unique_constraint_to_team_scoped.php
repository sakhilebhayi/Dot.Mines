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
        Schema::table('roles', function (Blueprint $table) {
            // Drop the global unique on name alone — this prevents two teams
            // from having a role with the same name (e.g. both having 'viewer').
            $table->dropUnique('roles_name_unique');

            // Replace with a composite unique scoped to team_id + name so role
            // names are unique within a team but can be reused across teams.
            $table->unique(['team_id', 'name'], 'roles_team_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_team_id_name_unique');
            $table->unique('name', 'roles_name_unique');
        });
    }
};
