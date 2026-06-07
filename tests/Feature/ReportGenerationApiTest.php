<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleService;
use App\Support\Reports\ReportGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function unauthenticated_request_to_generate_is_rejected(): void
    {
        $this->postJson('/api/v1/reports', [
            'title' => 'Test',
            'type' => 'production',
        ])->assertUnauthorized();
    }

    #[Test]
    public function unauthenticated_request_to_list_reports_is_rejected(): void
    {
        $this->getJson('/api/v1/reports')->assertUnauthorized();
    }

    #[Test]
    public function generate_requires_title_field(): void
    {
        Queue::fake();
        [$user] = $this->createAdminUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', ['type' => 'production'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function generate_requires_type_field(): void
    {
        Queue::fake();
        [$user] = $this->createAdminUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', ['title' => 'My Report'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function generate_rejects_invalid_type_value(): void
    {
        Queue::fake();
        [$user] = $this->createAdminUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'title' => 'My Report',
                'type' => 'not_a_real_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function generate_rejects_invalid_format_value(): void
    {
        Queue::fake();
        [$user] = $this->createAdminUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'title' => 'My Report',
                'type' => 'production',
                'format' => 'docx',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }

    #[Test]
    public function viewer_role_without_create_reports_permission_is_forbidden(): void
    {
        Queue::fake();
        $user = $this->createViewerUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'title' => 'My Report',
                'type' => 'production',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function viewer_role_can_list_reports(): void
    {
        $user = $this->createViewerUser();
        Report::factory()->count(2)->create([
            'team_id' => $user->current_team_id,
            'generated_by' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function download_returns_not_found_when_report_is_not_completed(): void
    {
        [$user, $team] = $this->createAdminUser();

        $report = Report::factory()->create([
            'team_id' => $team->id,
            'generated_by' => $user->id,
            'status' => 'pending',
            'file_path' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/reports/{$report->id}/download")
            ->assertNotFound();
    }

    #[Test]
    public function stats_endpoint_returns_expected_structure(): void
    {
        [$user, $team] = $this->createAdminUser();

        Report::factory()->count(3)->create([
            'team_id' => $team->id,
            'generated_by' => $user->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/stats')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function templates_endpoint_returns_available_types(): void
    {
        [$user] = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/templates')
            ->assertOk();

        $templates = $response->json('data');
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);

        foreach ($templates as $template) {
            $this->assertArrayHasKey('type', $template);
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('formats', $template);
        }
    }

    #[Test]
    public function generate_endpoint_is_throttled_after_ten_requests(): void
    {
        Queue::fake();
        RateLimiter::clear('reports');

        [$user] = $this->createAdminUser();

        // 10 successful requests
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/reports', [
                    'title' => "Report {$i}",
                    'type' => 'production',
                ])
                ->assertAccepted();
        }

        // 11th should be rate-limited
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'title' => 'Report 11',
                'type' => 'production',
            ])
            ->assertStatus(429);
    }

    /**
     * @return array<mixed>
     */
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

    /** Create a user with the 'viewer' role — has view_reports but NOT create_reports. */
    private function createViewerUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => true,
        ]);

        $user->forceFill(['current_team_id' => $team->id])->save();
        TeamRoleService::provisionTeam($team, $user);

        // Detach any admin role provisioned, then assign viewer
        $user->roles()->detach();
        $viewerRole = Role::where('team_id', $team->id)->where('name', 'viewer')->firstOrFail();
        $user->roles()->attach($viewerRole);

        return $user->fresh() ?? $user;
    }
}
