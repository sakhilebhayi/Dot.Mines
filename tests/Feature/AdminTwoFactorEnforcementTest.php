<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * C2 slice of the #27 split: admin-role accounts must have confirmed
 * two-factor authentication before using the authenticated app, enforced
 * by the admin.2fa middleware on the main route group. Non-admins are
 * unaffected, and the redirect target (Jetstream's profile page, where
 * 2FA is enabled) lives outside the enforced group so no redirect loop
 * is possible.
 */
class AdminTwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team}
     */
    private function makeUserWithTeam(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return [$user, $team];
    }

    private function makeAdmin(User $user, Team $team): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'admin', 'team_id' => $team->id],
            ['display_name' => 'Admin']
        );
        $user->roles()->attach($role);
    }

    public function test_admin_without_confirmed_2fa_is_redirected_to_profile(): void
    {
        [$user, $team] = $this->makeUserWithTeam();
        $this->makeAdmin($user, $team);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('profile.show'));
    }

    public function test_admin_without_confirmed_2fa_gets_403_on_json_requests(): void
    {
        [$user, $team] = $this->makeUserWithTeam();
        $this->makeAdmin($user, $team);

        $response = $this->actingAs($user)->getJson('/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_with_confirmed_2fa_can_use_the_app(): void
    {
        [$user, $team] = $this->makeUserWithTeam();
        $this->makeAdmin($user, $team);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_non_admin_without_2fa_is_unaffected(): void
    {
        [$user] = $this->makeUserWithTeam();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_admin_can_still_reach_the_profile_page_to_enable_2fa(): void
    {
        [$user, $team] = $this->makeUserWithTeam();
        $this->makeAdmin($user, $team);

        $response = $this->actingAs($user)->get('/user/profile');

        $response->assertOk();
    }

    public function test_ensure_admin_middleware_blocks_non_admins_and_admits_admins(): void
    {
        Route::middleware(['web', 'auth', 'admin'])->get('/_test-admin-only', fn () => 'ok');

        [$user, $team] = $this->makeUserWithTeam();
        $this->actingAs($user)->get('/_test-admin-only')->assertForbidden();

        $this->makeAdmin($user, $team);
        $this->actingAs($user)->get('/_test-admin-only')->assertOk();
    }
}
