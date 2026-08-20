<?php

namespace Tests\Feature;

use App\Mail\ReportReadyMail;
use App\Mail\WelcomeMail;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Generated reports and outbound email must carry the Dot.Mines identity:
 * the PDF renders a branded header (wordmark + embedded logo) and a
 * repeating official footer; every mailable extends the branded layout
 * (logo served via asset() so the URL is correct per environment).
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_pdf_view_carries_the_brand_header_and_official_footer(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'name' => 'Brand Test Mine']);

        $report = Report::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'August Production Summary',
            'type' => 'production',
            'status' => 'completed',
            'filters' => ['start_date' => '2026-08-01', 'end_date' => '2026-08-20'],
        ]);

        $html = view('reports.pdf.generated', [
            'report' => $report->fresh('team'),
            'data' => ['summary' => [], 'headers' => ['Date', 'Tonnes'], 'rows' => []],
        ])->render();

        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertStringContainsString('Operational Report', $html);
        $this->assertStringContainsString('Official '.config('app.name').' operational report', $html);
        $this->assertStringContainsString('Brand Test Mine', $html);
        // Logo embedded as a data URI -- no environment-dependent URL.
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_report_ready_and_welcome_mails_render_the_branded_layout(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $report = Report::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Fleet Utilization',
            'type' => 'fleet_utilization',
            'status' => 'completed',
        ]);

        $reportHtml = (new ReportReadyMail($report->fresh('team'), 'https://example.test/download'))->render();
        $welcomeHtml = (new WelcomeMail($user))->render();

        foreach ([$reportHtml, $welcomeHtml] as $html) {
            // Branded layout markers: logo image via asset() and the brand card.
            $this->assertStringContainsString('images/mark.png', $html);
            $this->assertStringContainsString('#d99e2b', $html);
            $this->assertStringContainsString(config('app.name'), $html);
        }

        $this->assertStringContainsString('Download Report', $reportHtml);
        $this->assertStringContainsString('Open Dashboard', $welcomeHtml);
    }
}
