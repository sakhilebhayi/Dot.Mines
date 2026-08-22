<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hybrid Slice 4: the admin system-health board (brief §33).
 */
class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->withPersonalTeam()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        TeamRoleProvisioner::assignRole($user, $user->currentTeam, 'admin');

        return $user;
    }

    public function test_admins_see_every_health_check(): void
    {
        $response = $this->actingAs($this->admin())->get('/system-health');

        $response->assertOk();
        $response->assertSeeInOrder(['Database', 'Queue', 'Bell API', 'Realtime push', 'Sync backbone']);
        $response->assertSee('polling covers freshness');
    }

    public function test_failed_jobs_surface_as_a_queue_warning(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get('/system-health');

        $response->assertOk();
        $response->assertSee('inspect failed_jobs');
    }

    public function test_non_admins_are_refused(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get('/system-health')->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/system-health')->assertRedirect();
    }
}
