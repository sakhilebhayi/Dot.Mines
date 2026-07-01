<?php

namespace App\Console\Commands;

use App\Services\Integration\BellHistoricalTelemetryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BellBackfillHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bell:backfill-history
                            {--from=2026-05-01 : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d, defaults to today)}
                            {--chunk-days=7 : Number of days per API request chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill Bell Equipment historical telemetry from the live API for a given date range';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromOption = $this->option('from');
        $from = Carbon::parse(is_string($fromOption) ? $fromOption : '2026-05-01')->startOfDay()->utc();
        $toOption = $this->option('to');
        $to = is_string($toOption)
            ? Carbon::parse($toOption)->endOfDay()->utc()
            : Carbon::now()->utc();
        $chunkDays = (int) $this->option('chunk-days');

        $service = new BellHistoricalTelemetryService(
            config('integrations.bell_historical.base_url'),
            config('integrations.bell_historical.api_username'),
            config('integrations.bell_historical.api_password'),
            config('integrations.bell_sso.token_url'),
            config('integrations.bell_sso.client_id'),
            config('integrations.bell_sso.client_secret'),
            config('integrations.bell_sso.scope'),
        );

        $this->info("Bell historical backfill: {$from->toDateString()} → {$to->toDateString()} in {$chunkDays}-day chunks");

        $totalFetched = 0;
        $totalInserted = 0;
        $totalSkipped = 0;

        $chunkStart = $from->copy();

        while ($chunkStart->lessThan($to)) {
            $chunkEnd = $chunkStart->copy()->addDays($chunkDays)->min($to);

            $fromStr = $chunkStart->format('Y-m-d\TH:i:s\Z');
            $toStr = $chunkEnd->format('Y-m-d\TH:i:s\Z');

            $this->line("  Chunk {$chunkStart->toDateString()} → {$chunkEnd->toDateString()} ...");

            try {
                $result = $service->syncRange($fromStr, $toStr);
                $totalFetched += $result['fetched'];
                $totalInserted += $result['inserted'];
                $totalSkipped += $result['skipped'];

                $this->line("    fetched={$result['fetched']} inserted={$result['inserted']} skipped={$result['skipped']}");
            } catch (\Throwable $e) {
                $this->error("    Failed: {$e->getMessage()}");
            }

            $chunkStart = $chunkEnd->copy()->addSecond();
        }

        $this->info("Done. Total: fetched={$totalFetched} inserted={$totalInserted} skipped={$totalSkipped}");

        return self::SUCCESS;
    }
}
