<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Services\Sync\SyncSequence;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin system-health board (hybrid spec Slice 4, brief §33): one page that
 * answers "which layer is unhappy?" -- database, queue, Bell ingestion,
 * broadcasting, and the sync backbone -- so troubleshooting starts from
 * evidence instead of guesswork. Read-only; every check is a cheap query.
 *
 * @psalm-suppress UnusedClass -- routed Livewire component
 * @psalm-suppress ClassMustBeFinal -- Livewire components stay extendable by convention here
 */
#[Layout('layouts.app')]
class SystemHealth extends Component
{
    private const STATE_HEALTHY = 'healthy';

    private const STATE_WARNING = 'warning';

    private const STATE_ERROR = 'error';

    /**
     * @return list<array{label: string, state: string, detail: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod -- Livewire computed property
     */
    public function getChecksProperty(): array
    {
        return [
            $this->databaseCheck(),
            $this->queueCheck(),
            $this->bellCheck(),
            $this->broadcastingCheck(),
            $this->syncCheck(),
        ];
    }

    /**
     * @return array{label: string, state: string, detail: string}
     */
    private function databaseCheck(): array
    {
        try {
            $driver = DB::connection()->getDriverName();
            DB::select('select 1');

            return ['label' => 'Database', 'state' => self::STATE_HEALTHY, 'detail' => "Connected ({$driver})"];
        } catch (\Throwable $exception) {
            return ['label' => 'Database', 'state' => self::STATE_ERROR, 'detail' => 'Unreachable: '.$exception->getMessage()];
        }
    }

    /**
     * @return array{label: string, state: string, detail: string}
     */
    private function queueCheck(): array
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $detail = "{$pending} pending, {$failed} failed";

        if ($failed > 0) {
            return ['label' => 'Queue', 'state' => self::STATE_WARNING, 'detail' => $detail.' — inspect failed_jobs'];
        }

        if ($pending > 200) {
            return ['label' => 'Queue', 'state' => self::STATE_WARNING, 'detail' => $detail.' — backlog building'];
        }

        return ['label' => 'Queue', 'state' => self::STATE_HEALTHY, 'detail' => $detail];
    }

    /**
     * @return array{label: string, state: string, detail: string}
     */
    private function bellCheck(): array
    {
        $integration = Integration::query()
            ->withoutGlobalScopes()
            ->where('provider', 'bell')
            ->orderByDesc('last_sync_at')
            ->first();

        if ($integration === null) {
            return ['label' => 'Bell API', 'state' => self::STATE_HEALTHY, 'detail' => 'No Bell integration configured'];
        }

        $lastSync = $integration->last_sync_at;
        $age = $lastSync !== null ? $lastSync->diffForHumans() : 'never';

        if ($integration->status !== 'connected') {
            return ['label' => 'Bell API', 'state' => self::STATE_WARNING, 'detail' => "Status {$integration->status}, last sync {$age}"];
        }

        if ($lastSync === null || $lastSync->lt(now()->subMinutes(45))) {
            return ['label' => 'Bell API', 'state' => self::STATE_WARNING, 'detail' => "Connected but last sync {$age}"];
        }

        return ['label' => 'Bell API', 'state' => self::STATE_HEALTHY, 'detail' => "Connected, last sync {$age}"];
    }

    /**
     * @return array{label: string, state: string, detail: string}
     */
    private function broadcastingCheck(): array
    {
        $driver = (string) config('broadcasting.default');
        $pusherKey = (string) config('broadcasting.connections.pusher.key');

        if ($driver === 'pusher' && $pusherKey !== '') {
            return ['label' => 'Realtime push', 'state' => self::STATE_HEALTHY, 'detail' => 'Pusher configured — clients push-updated'];
        }

        // Not an error: polling is the sanctioned fallback (brief §37).
        return ['label' => 'Realtime push', 'state' => self::STATE_HEALTHY, 'detail' => "Not configured ({$driver}) — polling covers freshness"];
    }

    /**
     * @return array{label: string, state: string, detail: string}
     */
    private function syncCheck(): array
    {
        $current = SyncSequence::current();

        if ($current === 0) {
            return ['label' => 'Sync backbone', 'state' => self::STATE_WARNING, 'detail' => 'No versions issued yet'];
        }

        /** @var string|null $lastWrite */
        $lastWrite = DB::table('sync_versions')->max('created_at');
        $age = $lastWrite === null ? 'unknown' : Carbon::parse($lastWrite)->diffForHumans();

        return [
            'label' => 'Sync backbone',
            'state' => self::STATE_HEALTHY,
            'detail' => "Version {$current}, last data change {$age}",
        ];
    }

    /** @psalm-suppress PossiblyUnusedMethod -- Livewire lifecycle */
    public function render(): View
    {
        return view('livewire.system-health');
    }
}
