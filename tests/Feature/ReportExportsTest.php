<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('supportedFormats')]
    public function test_generate_report_job_completes_supported_formats(string $format, string $extension, string $signature): void
    {
        Storage::fake('local');
        Mail::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $report = Report::create([
            'team_id' => $team->id,
            'generated_by' => $user->id,
            'title' => 'Operations Summary',
            'type' => 'production',
            'format' => $format,
            'status' => 'pending',
            'filters' => [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ]);

        (new GenerateReportJob($report))->handle();

        $report->refresh();

        $this->assertSame('completed', $report->status);
        $this->assertNotNull($report->generated_at);
        $this->assertStringEndsWith('.'.$extension, $report->file_path ?? '');
        Storage::assertExists($report->file_path);

        $contents = Storage::disk('local')->get($report->file_path);
        $this->assertStringStartsWith($signature, $contents);
    }

    public static function supportedFormats(): array
    {
        return [
            'pdf' => ['pdf', 'pdf', '%PDF'],
            'csv' => ['csv', 'csv', "\xEF\xBB\xBF"],
            'xlsx' => ['xlsx', 'xlsx', 'PK'],
        ];
    }
}
