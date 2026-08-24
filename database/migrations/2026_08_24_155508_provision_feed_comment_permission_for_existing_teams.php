<?php

use App\Models\Team;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Push comment_feed out to existing teams -- the provisioner only runs on
 * team creation or role assignment; same idempotent pattern as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Team::query()->each(function (Team $team): void {
            TeamRoleProvisioner::provisionForTeam($team);
        });
    }

    public function down(): void {}
};
