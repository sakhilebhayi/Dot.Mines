<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeam(): Team
    {
        $user = User::factory()->create();

        return Team::factory()->create(['user_id' => $user->id]);
    }

    #[Test]
    public function job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $report = Report::factory()->create(['team_id' => $this->makeTeam()->id]);

        GenerateReportJob::dispatch($report);

        Queue::assertPushed(GenerateReportJob::class);
    }

    #[Test]
    public function job_has_correct_retry_and_timeout_configuration(): void
    {
        $report = Report::factory()->create(['team_id' => $this->makeTeam()->id]);
        $job = new GenerateReportJob($report);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }

    #[Test]
    public function job_marks_report_failed_on_failure(): void
    {
        $report = Report::factory()->create([
            'team_id' => $this->makeTeam()->id,
            'status' => 'processing',
        ]);

        (new GenerateReportJob($report))->failed(new \RuntimeException('Simulated failure'));

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'failed']);
    }

    #[Test]
    public function failed_method_is_safe_for_already_failed_report(): void
    {
        $report = Report::factory()->failed()->create(['team_id' => $this->makeTeam()->id]);

        (new GenerateReportJob($report))->failed(new \RuntimeException('Second failure'));

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'failed']);
    }

    #[Test]
    public function job_generates_csv_fuel_report_and_marks_completed(): void
    {
        Storage::fake('local');

        $report = Report::factory()->create([
            'team_id' => $this->makeTeam()->id,
            'type' => 'fuel',
            'format' => 'csv',
            'status' => 'pending',
            'filters' => [],
        ]);

        (new GenerateReportJob($report))->handle();

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->file_path);
        $this->assertNotNull($report->generated_at);
    }

    #[Test]
    public function job_generates_maintenance_csv_report(): void
    {
        Storage::fake('local');

        $report = Report::factory()->create([
            'team_id' => $this->makeTeam()->id,
            'type' => 'maintenance',
            'format' => 'csv',
            'status' => 'pending',
            'filters' => [],
        ]);

        (new GenerateReportJob($report))->handle();

        $this->assertEquals('completed', $report->fresh()->status);
    }
}
