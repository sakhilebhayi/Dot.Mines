<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\TeamRoleService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = Team::all();

        if ($teams->isEmpty()) {
            $this->command->warn('No teams found. Create a team first using the application.');

            return;
        }

        foreach ($teams as $team) {
            // Provision roles and permissions for each team.
            // For existing teams we pass null as the owner so no role reassignment happens.
            TeamRoleService::provisionTeam($team, owner: null);

            $this->command->info("Roles and permissions processed for team: {$team->name}");
        }
    }
}
