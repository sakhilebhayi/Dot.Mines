<?php

use App\Models\Team;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Push the feed permissions (view_feed / post_feed / pin_feed) out to teams
 * that already exist -- same idempotent re-provision pattern as the operator
 * permissions, and for the same reason: the provisioner only runs on team
 * creation or role assignment, so existing teams never see new catalogue
 * entries on their own.
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
        // Leaving permissions in place is harmless; removing them could strip
        // grants an admin has edited by hand.
    }
};
