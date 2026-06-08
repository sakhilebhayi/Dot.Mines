<?php

namespace Tests\Feature;

use App\Mail\ReportReadyMail;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportReadyMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function report_ready_mail_is_queued_to_notifications_queue(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $report = Report::factory()->create(['team_id' => $user->currentTeam->id, 'status' => 'completed']);

        $mailable = new ReportReadyMail($report);

        $this->assertSame('notifications', $mailable->queue);
    }

    #[Test]
    public function report_ready_mail_subject_contains_report_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $report = Report::factory()->create(['team_id' => $user->currentTeam->id, 'title' => 'Monthly Fuel Report']);

        $mailable = new ReportReadyMail($report);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Monthly Fuel Report', $envelope->subject);
    }

    #[Test]
    public function report_ready_mail_renders_with_report_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $report = Report::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Q2 Production Report',
            'status' => 'completed',
        ]);

        $mailable = new ReportReadyMail($report);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Q2 Production Report', $rendered);
    }

    #[Test]
    public function report_ready_mail_has_plain_text_view(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $report = Report::factory()->create(['team_id' => $user->currentTeam->id]);

        $mailable = new ReportReadyMail($report);
        $content = $mailable->content();

        $this->assertNotNull($content->text);
    }

    #[Test]
    public function report_ready_mail_queues_when_report_is_marked_completed(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        // Add a team member so $team->users() returns at least one recipient
        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'operator']);

        $report = Report::factory()->create([
            'team_id' => $team->id,
            'status' => 'pending',
        ]);

        // markCompleted takes a file path (string); it derives recipients from team users internally
        $report->markCompleted('reports/q2-production.pdf', 102400);

        Mail::assertQueued(ReportReadyMail::class);
    }
}
