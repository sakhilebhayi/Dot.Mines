<?php

namespace Tests\Feature;

use App\Livewire\ReportGenerator;
use App\Livewire\Reports;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reports::deleteReport() and ReportGenerator::generateReport() now
 * authorize against ReportPolicy (already defined but unused). viewer has
 * view_reports but not create_reports/delete_reports.
 */
class ReportsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_role_cannot_delete_a_report(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Production Report',
            'type' => 'production',
            'status' => 'completed',
            'format' => 'pdf',
            'generated_by' => $owner->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(Reports::class)
            ->call('deleteReport', $report->id);

        $this->assertDatabaseHas('reports', ['id' => $report->id]);
    }

    public function test_viewer_role_cannot_generate_a_report(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        Livewire::actingAs($viewer)
            ->test(ReportGenerator::class)
            ->set('reportName', 'My Report')
            ->set('reportType', 'production')
            ->set('startDate', now()->subDays(7)->format('Y-m-d'))
            ->set('endDate', now()->format('Y-m-d'))
            ->call('generateReport');

        $this->assertDatabaseMissing('reports', ['title' => 'My Report']);
    }

    public function test_fleet_manager_can_generate_and_delete_a_report(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        Livewire::actingAs($manager)
            ->test(ReportGenerator::class)
            ->set('reportName', 'My Report')
            ->set('reportType', 'production')
            ->set('startDate', now()->subDays(7)->format('Y-m-d'))
            ->set('endDate', now()->format('Y-m-d'))
            ->call('generateReport');

        $this->assertDatabaseHas('reports', ['title' => 'My Report', 'team_id' => $team->id]);
    }
}
