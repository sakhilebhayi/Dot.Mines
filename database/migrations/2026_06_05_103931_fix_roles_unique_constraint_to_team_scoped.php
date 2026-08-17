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
            if (Schema::hasIndex('roles', 'roles_name_unique')) {
                $table->dropUnique('roles_name_unique');
            }

            if (! Schema::hasIndex('roles', 'roles_team_id_name_unique')) {
                $table->unique(['team_id', 'name'], 'roles_team_id_name_unique');
            }
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
