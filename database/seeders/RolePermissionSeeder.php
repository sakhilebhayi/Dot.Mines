<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The actual permission/role catalog lives in TeamRoleProvisioner so the
     * same definitions are used here, when a team is created at
     * registration (App\Actions\Fortify\CreateNewUser,
     * App\Actions\Jetstream\CreateTeam), and when a member is invited or
     * re-roled (App\Livewire\Settings).
     */
    public function run(): void
    {
        $teams = Team::all();

        if ($teams->isEmpty()) {
            $this->command->warn('No teams found. Create a team first using the application.');

            return;
        }

        foreach ($teams as $team) {
            TeamRoleProvisioner::provisionForTeam($team);
            $this->command->info("Roles and permissions created for team: {$team->name}");
        }
    }
}
