<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: the page's download link used to point straight at
 * Storage::url($report->file_path) -- a raw storage URL with none of
 * ReportDownloadController's checks (signed-URL requirement, team match,
 * completed-status, path traversal). It now routes through the existing,
 * already-secured reports.signed-download controller instead. Confirms the
 * page renders and the download link points at the signed route.
 */
class ReportShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_report_show(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Test Report',
            'type' => 'production',
            'status' => 'completed',
            'format' => 'pdf',
            'file_path' => 'reports/test.pdf',
            'generated_by' => $owner->id,
        ]);

        $response = $this->get("/reports/{$report->id}");

        $response->assertRedirect('/login');
    }

    public function test_report_owner_sees_a_signed_download_link_not_a_raw_storage_url(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);

        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Test Report',
            'type' => 'production',
            'status' => 'completed',
            'format' => 'pdf',
            'file_path' => 'reports/test.pdf',
            'generated_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get("/reports/{$report->id}");

        $response->assertOk();
        $response->assertSee('Test Report');
        $response->assertSee(route('reports.signed-download', ['report' => $report->id]), false);
        $response->assertDontSee('/storage/reports/test.pdf');
    }
}
