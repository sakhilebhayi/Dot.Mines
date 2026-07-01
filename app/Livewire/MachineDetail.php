<?php

namespace App\Livewire;

use App\Models\BellDefLevel;
use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentDailyKpi;
use App\Models\BellEquipmentFuelUsageHistory;
use App\Models\BellEquipmentLocationHistory;
use App\Models\BellEquipmentOperatingHoursHistory;
use App\Models\BellRegenerationHour;
use App\Models\Machine;
use App\Services\MachineTelemetryService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MachineDetail extends Component
{
    public Machine $machine;

    public function mount(Machine $machine): void
    {
        if ($machine->team_id !== Auth::user()->currentTeam->id) {
            abort(403);
        }
        $this->machine = $machine;
    }

    public function render(): View
    {
        $metrics = $this->machine->metrics()->latest('created_at')->take(10)->get();
        $recentAlerts = $this->machine->alerts()->latest('created_at')->take(5)->get();

        $isBellTeam = $this->machine->team_id === (int) config('integrations.bell.team_id');
        $bellEquipment = $isBellTeam
            ? BellEquipment::where('machine_id', $this->machine->id)->first()
            : null;

        // Live telemetry snapshot (fuel, engine, hours, status, location, speed)
        $liveTelemetry = app(MachineTelemetryService::class)->forMachine($this->machine->id);

        $bellStatus = null;
        $bellCautionCodes = collect();
        $bellFuelHistory = collect();
        $bellOpHoursHistory = collect();
        $bellLoadHistory = collect();
        $bellLocationHistory = collect();
        $bellDefHistory = collect();
        $bellRegenHistory = collect();
        $productionToday = null;

        if ($bellEquipment !== null) {
            $key = $bellEquipment->equipment_key;
            $mayStart = Carbon::parse('2026-05-01')->startOfDay();
            $today = Carbon::today();

            $bellStatus = BellEquipmentCurrentStatus::where('equipment_key', $key)->first();

            $bellCautionCodes = BellEquipmentCautionCode::where('equipment_key', $key)
                ->where('is_active', true)
                ->orderByDesc('occurred_at')
                ->get();

            $bellFuelHistory = BellEquipmentFuelUsageHistory::where('equipment_key', $key)
                ->where('recorded_at', '>=', $mayStart)
                ->whereNotNull('fuel_remaining_percent')
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'fuel_remaining_percent']);

            $bellOpHoursHistory = BellEquipmentOperatingHoursHistory::where('equipment_key', $key)
                ->where('recorded_at', '>=', $mayStart)
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'operating_hours']);

            $bellLoadHistory = BellEquipmentDailyKpi::where('equipment_key', $key)
                ->where('kpi_date', '>=', $mayStart)
                ->orderBy('kpi_date')
                ->get(['kpi_date', 'loads_moved', 'payload_moved']);

            $bellLocationHistory = BellEquipmentLocationHistory::where('equipment_key', $key)
                ->where('recorded_at', '>=', $mayStart)
                ->orderByDesc('recorded_at')
                ->limit(500)
                ->get(['recorded_at', 'latitude', 'longitude', 'heading_degrees', 'speed_kmh']);

            $bellDefHistory = BellDefLevel::where('equipment_key', $key)
                ->where('snapshot_time', '>=', $mayStart)
                ->orderBy('snapshot_time')
                ->get(['snapshot_time as recorded_at', 'def_remaining_percent']);

            $bellRegenHistory = BellRegenerationHour::where('equipment_key', $key)
                ->where('snapshot_time', '>=', $mayStart)
                ->orderBy('snapshot_time')
                ->get(['snapshot_time as recorded_at', 'regeneration_hours']);

            // Today's production KPI (24-hour rolling)
            $productionToday = BellEquipmentDailyKpi::where('equipment_key', $key)
                ->whereDate('kpi_date', $today)
                ->first();
        }

        return view('livewire.machine-detail', [
            'metrics' => $metrics,
            'recentAlerts' => $recentAlerts,
            'liveTelemetry' => $liveTelemetry,
            'bellEquipment' => $bellEquipment,
            'bellStatus' => $bellStatus,
            'bellCautionCodes' => $bellCautionCodes,
            'bellFuelHistory' => $bellFuelHistory,
            'bellOpHoursHistory' => $bellOpHoursHistory,
            'bellLoadHistory' => $bellLoadHistory,
            'bellLocationHistory' => $bellLocationHistory,
            'bellDefHistory' => $bellDefHistory,
            'bellRegenHistory' => $bellRegenHistory,
            'productionToday' => $productionToday,
        ]);
    }
}
