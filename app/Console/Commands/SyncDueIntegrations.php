<?php

namespace App\Console\Commands;

use App\Jobs\SyncIntegrationMachinesJob;
use App\Models\Integration;
use Illuminate\Console\Command;

/**
 * Nothing previously scheduled SyncIntegrationMachinesJob (or any of the
 * other manufacturer sync jobs) for any of the 25 providers -- machine and
 * metric data only ever synced if a user manually clicked "Sync Now" or hit
 * the API endpoint directly. Each manufacturer already declares its own
 * `sync_interval` in config('integrations.manufacturers.*') (Bell: 900s /
 * 15 minutes, most others: 300s / 5 minutes), but that value had never
 * actually been read anywhere. Scheduled every 5 minutes (the finest
 * interval any manufacturer declares) in routes/console.php; each run only
 * dispatches integrations whose own interval has actually elapsed.
 */
class SyncDueIntegrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:sync-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a machine/metric sync for every connected integration whose configured sync interval has elapsed';

    public function handle(): int
    {
        $connected = Integration::where('status', 'connected')->get();
        $dispatched = 0;

        foreach ($connected as $integration) {
            if (! $this->isDue($integration)) {
                continue;
            }

            SyncIntegrationMachinesJob::dispatch($integration);
            $dispatched++;
        }

        $this->info("Dispatched sync for {$dispatched} of {$connected->count()} connected integration(s).");

        return self::SUCCESS;
    }

    private function isDue(Integration $integration): bool
    {
        if (! $integration->last_sync_at) {
            return true; // Never synced -- always due.
        }

        /** @psalm-suppress MixedAssignment */
        $intervalRaw = config("integrations.manufacturers.{$integration->provider}.sync_interval")
            ?? config('integrations.jobs.machines_sync_interval', 300);
        $intervalSeconds = is_numeric($intervalRaw) ? (int) $intervalRaw : 300;

        return $integration->last_sync_at->addSeconds($intervalSeconds)->isPast();
    }
}
