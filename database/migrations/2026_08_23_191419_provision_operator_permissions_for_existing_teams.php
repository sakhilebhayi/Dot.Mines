<?php

use App\Models\Team;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Push the new operator permissions out to teams that already exist.
 *
 * TeamRoleProvisioner only runs when a team is created or a role assigned,
 * so a permission added to the catalogue never reaches existing teams on its
 * own -- their admins would see the Operators page 403 until someone
 * re-assigned a role. provisionForTeam() is idempotent (firstOrCreate + a
 * role->permissions sync), so re-running it here is safe and picks up
 * view/manage_operators and the medical permissions for every current team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Team::query()->each(function (Team $team): void {
            TeamRoleProvisioner::provisionForTeam($team);
        });
    }

    public function down(): void
    {
        // Permissions are harmless to leave in place; removing them could
        // strip grants an admin has since edited by hand.
    }
};
