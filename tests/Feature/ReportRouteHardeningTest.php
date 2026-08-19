<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /reports/{report} used to accept any string: a non-numeric id reached
 * Postgres as `where id = 'view-2'` and died with SQLSTATE 22P02 (a 500)
 * instead of a 404. The legacy reports/view-2 and reports/generate/simple
 * placeholder routes that exposed this are removed, and the parameterized
 * report routes are now constrained to numeric ids.
 */
class ReportRouteHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return $user;
    }

    public function test_non_numeric_report_id_is_a_404_not_a_database_error(): void
    {
        $this->actingAs($this->actingUser())
            ->get('/reports/view-2')
            ->assertNotFound();
    }

    public function test_non_numeric_report_download_id_is_a_404(): void
    {
        $this->actingAs($this->actingUser())
            ->get('/reports/not-a-report/download')
            ->assertNotFound();
    }

    public function test_legacy_placeholder_generate_route_is_gone(): void
    {
        $this->actingAs($this->actingUser())
            ->get('/reports/generate/simple')
            ->assertNotFound();
    }

    public function test_real_report_generator_still_loads(): void
    {
        $this->actingAs($this->actingUser())
            ->get('/reports/generate')
            ->assertOk();
    }
}
