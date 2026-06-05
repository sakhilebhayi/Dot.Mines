<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Reports\ReportGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_generation_accepts_array_filters_and_livewire_report_types(): void
    {
        Queue::fake();

        [$user, $team] = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
            'title' => 'Daily Production',
            'type' => 'production',
            'format' => 'csv',
            'filters' => [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'machine_ids' => ['12', '18'],
            ],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'production');

        $report = Report::firstOrFail();

        $this->assertSame($team->id, $report->team_id);
        $this->assertSame([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'machine_ids' => ['12', '18'],
        ], $report->filters);

        Queue::assertPushed(GenerateReportJob::class, function ($job) {
            return $job->connection === ReportGeneration::preferredQueueConnection();
        });
    }

    private function createAdminUser(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => true,
        ]);

        $user->forceFill(['current_team_id' => $team->id])->save();

        $role = Role::create([
            'team_id' => $team->id,
            'name' => 'admin',
            'display_name' => 'Admin',
        ]);

        $user->roles()->attach($role);

        return [$user, $team];
    }
}
