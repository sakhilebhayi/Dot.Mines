<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        // These four keys/names must match App\Services\TeamRoleProvisioner's
        // role catalog exactly -- AppServiceProvider syncs Jetstream's team_user
        // pivot role into that RBAC system on every add/invite/role-change, and
        // a mismatched key here would silently leave a team member with no
        // real permissions despite Jetstream showing them a role.
        Jetstream::role('admin', 'Administrator', [
            'create',
            'read',
            'update',
            'delete',
        ])->description('Full access to every feature and data set. Team membership and billing remain Owner-only.');

        Jetstream::role('fleet_manager', 'Fleet Manager', [
            'create',
            'read',
            'update',
        ])->description('Can manage machines, geofences, and reports, but not team or billing settings.');

        Jetstream::role('operator', 'Operator', [
            'read',
        ])->description('Can view machines and maps, and acknowledge alerts.');

        Jetstream::role('viewer', 'Viewer', [
            'read',
        ])->description('Read-only access to dashboards, machines, and reports.');
    }
}
