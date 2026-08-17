<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Livewire\ReportGenerator;
use App\Mail\ReportReadyMail;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\ReportDataService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test: ReportGenerator::generateReport() created a Report row
 * and told the user "You will receive an email when ready", but nothing in
 * the app ever processed a pending report -- GenerateReportJob was never
 * dispatched, and Report::markCompleted() (which sends that email) was
 * never called from anywhere. Every report was permanently stuck at
 * status='pending'. This proves the full real pipeline now runs end to end
 * for all three formats.
 */
class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUserWithFuelData(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'machine_id' => $machine->id,
            'transaction_type' => 'dispensing',
            'transaction_date' => now()->subDays(1),
            'quantity_liters' => 100,
            'unit_price' => 25,
            'total_cost' => 2500,
        ]);

        return [$user, $team];
    }

    public function test_generating_a_csv_report_actually_completes_it_with_a_real_file(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$user] = $this->actingUserWithFuelData();

        Livewire::actingAs($user)
            ->test(ReportGenerator::class)
            ->set('reportName', 'Fuel Report')
            ->set('reportType', 'fuel_consumption')
            ->set('startDate', now()->subDays(7)->toDateString())
            ->set('endDate', now()->toDateString())
            ->set('format', 'csv')
            ->call('generateReport');

        $report = Report::firstWhere('title', 'Fuel Report');
        $this->assertNotNull($report);
        $this->assertSame('completed', $report->status);
        $this->assertNotNull($report->file_path);
        Storage::disk('local')->assertExists($report->file_path);

        $contents = Storage::disk('local')->get($report->file_path);
        $this->assertStringContainsString('100', $contents); // real quantity_liters, not a placeholder
    }

    public function test_generating_a_pdf_report_actually_completes_it(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$user] = $this->actingUserWithFuelData();

        Livewire::actingAs($user)
            ->test(ReportGenerator::class)
            ->set('reportName', 'Fuel Report PDF')
            ->set('reportType', 'fuel_consumption')
            ->set('startDate', now()->subDays(7)->toDateString())
            ->set('endDate', now()->toDateString())
            ->set('format', 'pdf')
            ->call('generateReport');

        $report = Report::firstWhere('title', 'Fuel Report PDF');
        $this->assertSame('completed', $report->status);
        Storage::disk('local')->assertExists($report->file_path);
        // A real PDF file, not an empty placeholder.
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
    }

    public function test_generating_an_xlsx_report_actually_completes_it(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$user] = $this->actingUserWithFuelData();

        Livewire::actingAs($user)
            ->test(ReportGenerator::class)
            ->set('reportName', 'Fuel Report XLSX')
            ->set('reportType', 'fuel_consumption')
            ->set('startDate', now()->subDays(7)->toDateString())
            ->set('endDate', now()->toDateString())
            ->set('format', 'xlsx')
            ->call('generateReport');

        $report = Report::firstWhere('title', 'Fuel Report XLSX');
        $this->assertSame('completed', $report->status);
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertGreaterThan(0, Storage::disk('local')->size($report->file_path));
    }

    public function test_completing_a_report_actually_queues_the_ready_email(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$user, $team] = $this->actingUserWithFuelData();

        Livewire::actingAs($user)
            ->test(ReportGenerator::class)
            ->set('reportName', 'Emailed Report')
            ->set('reportType', 'fuel_consumption')
            ->set('startDate', now()->subDays(7)->toDateString())
            ->set('endDate', now()->toDateString())
            ->set('format', 'csv')
            ->call('generateReport');

        Mail::assertQueued(ReportReadyMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * "Email Reports" was a real, saved Settings preference that nothing
     * ever read -- every team member got this email regardless of whether
     * they'd turned it off.
     */
    public function test_report_ready_email_is_not_sent_to_a_member_who_opted_out(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$owner, $team] = $this->actingUserWithFuelData();

        $optedOut = User::factory()->create(['notification_preferences' => ['email_reports' => false]]);
        $team->users()->attach($optedOut, ['role' => 'operator']);

        Livewire::actingAs($owner)
            ->test(ReportGenerator::class)
            ->set('reportName', 'Opted Out Report')
            ->set('reportType', 'fuel_consumption')
            ->set('startDate', now()->subDays(7)->toDateString())
            ->set('endDate', now()->toDateString())
            ->set('format', 'csv')
            ->call('generateReport');

        Mail::assertQueued(ReportReadyMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });
        Mail::assertNotQueued(ReportReadyMail::class, function ($mail) use ($optedOut) {
            return $mail->hasTo($optedOut->email);
        });
    }

    public function test_an_unsupported_report_type_fails_cleanly_with_a_real_error_instead_of_hanging_at_pending(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();

        // Mirrors what the (currently unused) API report endpoint accepts --
        // types ReportDataService doesn't know how to build yet.
        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Sensor Report',
            'type' => 'truck_sensors',
            'format' => 'pdf',
            'status' => 'pending',
            'filters' => [],
        ]);

        (new GenerateReportJob($report))->handle(app(ReportDataService::class));

        $report->refresh();
        $this->assertSame('failed', $report->status);
        $this->assertNotNull($report->error_message);
        $this->assertStringContainsString('truck_sensors', $report->error_message);
    }
}
