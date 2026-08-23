<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Services\ProductionLossService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Scans every active machine's telemetry for the given day (default:
 * yesterday, so a full day's window is available) and raises POTENTIAL
 * production-loss events: the machine was connected and marked active, but
 * its engine-hours meter did not move. Detected events are created as
 * pending_classification and are never counted as confirmed losses until a
 * person reviews them on the Machine Details page.
 */
class DetectProductionLosses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'production:detect-losses {--date= : Day to scan (Y-m-d, defaults to yesterday)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect potential production-loss events from machine telemetry for human review';

    public function handle(ProductionLossService $service): int
    {
        $dateOption = $this->option('date');
        $day = is_string($dateOption) && $dateOption !== ''
            ? Carbon::parse($dateOption)
            : now()->subDay();

        $detected = 0;

        Machine::query()
            ->where('status', 'active')
            ->whereHas('metrics', fn ($query): mixed => $query->whereBetween('recorded_at', [
                $day->copy()->startOfDay(),
                $day->copy()->endOfDay(),
            ]))
            ->with('team')
            ->chunkById(100, function ($machines) use ($service, $day, &$detected) {
                foreach ($machines as $machine) {
                    if ($service->detectForDay($machine, $day->copy()) !== null) {
                        $detected++;
                    }
                }
            });

        $this->info("Scanned telemetry for {$day->toDateString()}: {$detected} potential production-loss event(s) raised for review.");

        return self::SUCCESS;
    }
}
