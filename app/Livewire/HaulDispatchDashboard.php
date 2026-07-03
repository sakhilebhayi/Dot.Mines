<?php

namespace App\Livewire;

use App\Models\HaulDispatch;
use App\Services\MachineTelemetryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HaulDispatchDashboard extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $activeDispatches = [];

    /** @var array<int, array<string, mixed>> Compact version passed to the map JS */
    public array $mapDispatches = [];

    public bool $isLoading = true;

    public string $statusFilter = 'all';

    public ?int $selectedDispatchId = null;

    public function mount(): void
    {
        $this->loadDispatches();
    }

    // ─── Data Loading ─────────────────────────────────────────────────────────

    public function loadDispatches(): void
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            $this->isLoading = false;

            return;
        }

        $query = HaulDispatch::forTeam($team->id)
            ->active()
            ->with(['machine', 'mineArea'])
            ->latest('started_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $dispatches = $query->get();

        // ── Enrich with live Bell telemetry ──────────────────────────────────
        $machineIds = $dispatches->pluck('machine_id')->filter()->unique()->all();
        $bellTelemetry = ! empty($machineIds)
            ? app(MachineTelemetryService::class)->forMachines($machineIds)
            : [];

        $this->activeDispatches = $dispatches->map(function (HaulDispatch $d) use ($bellTelemetry): array {
            $capacity = $d->fuel_capacity_litres ?? $d->machine?->fuel_capacity ?? 0;
            $fuelLevel = $d->current_fuel_level_litres ?? 0;
            $machineCapacity = $d->machine?->capacity ?? 0;

            // Prefer live Bell fuel % over the dispatch record's stale value.
            $tel = $bellTelemetry[$d->machine_id ?? 0] ?? null;
            $liveFuelPct = $tel !== null && $tel['fuel_remaining_percent'] !== null
                ? (float) $tel['fuel_remaining_percent']
                : $d->fuel_percentage;

            return [
                'id' => $d->id,
                'machine_id' => $d->machine_id,
                'machine_name' => $d->machine?->name ?? 'Unknown',
                'machine_type' => $d->machine?->machine_type ?? 'haul_truck',
                'status' => $d->status,
                'origin_name' => $d->origin_name ?? 'Loading Point',
                'destination_name' => $d->destination_name ?? 'Dump Point',
                'current_latitude' => $d->current_latitude,
                'current_longitude' => $d->current_longitude,
                'current_heading' => $d->current_heading ?? 0,
                'current_speed_kmh' => $tel['speed_kmh'] ?? $d->current_speed_kmh ?? 0,
                'current_tonnage' => $d->current_tonnage ?? 0,
                'machine_capacity' => $machineCapacity,
                'fuel_percentage' => $liveFuelPct,
                'current_fuel_level_litres' => $fuelLevel,
                'fuel_capacity_litres' => $capacity,
                'eta_formatted' => $d->eta_formatted,
                'started_at' => $d->started_at?->diffForHumans() ?? 'Not started',
                'total_distance_km' => $d->total_distance_km ?? 0,
                'distance_remaining_km' => $d->distance_remaining_km ?? 0,
                'mine_area' => $d->mineArea?->name ?? '—',
                // Live Bell telemetry extras
                'bell_status' => $tel['status_label'] ?? null,
                'bell_engine_running' => $tel['engine_running'] ?? null,
                'bell_last_seen' => $tel['last_seen_human'] ?? null,
                // Map-specific coords
                'origin_lat' => $d->origin_latitude,
                'origin_lng' => $d->origin_longitude,
                'dest_lat' => $d->destination_latitude,
                'dest_lng' => $d->destination_longitude,
                'path_coordinates' => $d->path_coordinates ?? [],
            ];
        })->toArray();

        // Compact version for the Leaflet map
        $this->mapDispatches = array_map(fn (array $d): array => [
            'id' => $d['id'],
            'machine_name' => $d['machine_name'],
            'status' => $d['status'],
            'current_lat' => $d['current_latitude'],
            'current_lng' => $d['current_longitude'],
            'heading' => $d['current_heading'],
            'origin_lat' => $d['origin_lat'],
            'origin_lng' => $d['origin_lng'],
            'origin_name' => $d['origin_name'],
            'dest_lat' => $d['dest_lat'],
            'dest_lng' => $d['dest_lng'],
            'dest_name' => $d['destination_name'],
            'path' => $d['path_coordinates'],
        ], $this->activeDispatches);

        // Push updated map data to Alpine
        $this->dispatch('haul-dispatch:map-data', dispatches: $this->mapDispatches);

        $this->isLoading = false;
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function filterByStatus(string $status): void
    {
        $allowed = ['all', 'loading', 'hauling', 'dumping', 'returning', 'idle'];
        if (! in_array($status, $allowed, true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->loadDispatches();
    }

    public function selectDispatch(int $id): void
    {
        $this->selectedDispatchId = $this->selectedDispatchId === $id ? null : $id;
        $this->dispatch('haul-dispatch:select', id: $this->selectedDispatchId);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.haul-dispatch-dashboard');
    }
}
