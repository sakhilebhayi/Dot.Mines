<?php

namespace App\Livewire;

use App\Jobs\GenerateReportJob;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\Machine;
use App\Models\Report;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ReportGenerator extends Component
{
    use NotifiesUser;

    public int $step = 1;

    public string $reportName = '';

    public string $reportType = 'production';

    public string $description = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $format = 'pdf';

    /** @var array<int, mixed> */
    public array $selectedMachines = [];

    /** @var array<int, mixed> */
    public array $selectedGeofences = [];

    public bool $includeMetrics = true;

    public bool $includeAlerts = true;

    public bool $includeChart = true;

    public bool $autoSchedule = false;

    public string $scheduleFrequency = 'weekly';

    /** @var array<string, array<string, string>> */
    protected array $reportTypes = [
        'production' => [
            'label' => 'Production Summary',
            'description' => 'Total material extracted, production rates, and efficiency metrics',
            'icon' => '📊',
        ],
        'fleet_utilization' => [
            'label' => 'Fleet Utilization',
            'description' => 'Machine availability, usage hours, and capacity utilization',
            'icon' => '🚜',
        ],
        'maintenance_schedule' => [
            'label' => 'Maintenance Schedule',
            'description' => 'Scheduled maintenance, service history, and upcoming services',
            'icon' => '🔧',
        ],
        'fuel_consumption' => [
            'label' => 'Fuel Consumption',
            'description' => 'Fuel usage, consumption rates, and cost analysis',
            'icon' => '⛽',
        ],
        'material_tracking' => [
            'label' => 'Material Tracking',
            'description' => 'Material movement, geofence entries/exits, and inventory',
            'icon' => '📦',
        ],
        'downtime_analysis' => [
            'label' => 'Downtime Analysis',
            'description' => 'Machine downtime events, root causes, and impact analysis',
            'icon' => '⏸️',
        ],
        'compliance' => [
            'label' => 'Compliance (MHSA/DMRE)',
            'description' => 'Violation register with remediation deadlines, resolution status, and compliance score for regulator submission',
            'icon' => '📋',
        ],
    ];

    /** @var array<string, string> */
    protected array $rules = [
        'reportName' => 'required|string|max:255',
        'reportType' => 'required|in:production,fleet_utilization,maintenance_schedule,fuel_consumption,material_tracking,downtime_analysis,compliance',
        'description' => 'nullable|string|max:1000',
        'startDate' => 'required|date|before_or_equal:today',
        'endDate' => 'required|date|after_or_equal:startDate|before_or_equal:today',
        'format' => 'required|in:pdf,csv,xlsx',
        'selectedMachines.*' => 'nullable|exists:machines,id',
        'selectedGeofences.*' => 'nullable|exists:geofences,id',
    ];

    /** @var array<string, string> */
    protected array $messages = [
        'reportName.required' => 'Please enter a report name.',
        'startDate.required' => 'Please select a start date.',
        'endDate.required' => 'Please select an end date.',
        'endDate.after_or_equal' => 'End date must be after or equal to start date.',
    ];

    public function mount(): void
    {
        $this->startDate = now()->subDays(30)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Machine>
     */
    public function getMachines(): \Illuminate\Database\Eloquent\Collection
    {
        $team = CurrentUser::get()?->currentTeam;

        return Machine::where('team_id', $team?->id)->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function getGeofences(): Collection
    {
        $team = CurrentUser::get()?->currentTeam;

        return DB::table('geofences')->where('team_id', $team?->id)->get();
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'reportName' => 'required|string|max:255',
                'reportType' => 'required',
                'description' => 'nullable|string|max:1000',
            ]);
            $this->step = 2;
        } elseif ($this->step === 2) {
            $this->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
            ]);
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function generateReport(): void
    {
        $this->validate();

        $user = CurrentUser::get();
        $team = $user?->currentTeam;

        if (! $team) {
            $this->notify('No team selected', 'error');

            return;
        }

        // Verify selected machines belong to team
        if (! empty($this->selectedMachines)) {
            $validMachineIds = Machine::where('team_id', $team->id)
                ->whereIn('id', $this->selectedMachines)
                ->pluck('id')
                ->toArray();
            $this->selectedMachines = $validMachineIds;
        }

        // Verify selected geofences belong to team
        if (! empty($this->selectedGeofences)) {
            $validGeofenceIds = DB::table('geofences')
                ->where('team_id', $team->id)
                ->whereIn('id', $this->selectedGeofences)
                ->pluck('id')
                ->values()
                ->all();
            $this->selectedGeofences = $validGeofenceIds;
        }

        try {
            $this->authorize('generate', Report::class);

            // Prepare filters array with sanitized data
            $filters = [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'machine_ids' => $this->selectedMachines,
                'geofence_ids' => $this->selectedGeofences,
                'include_metrics' => $this->includeMetrics,
                'include_alerts' => $this->includeAlerts,
                'include_chart' => $this->includeChart,
                'auto_schedule' => $this->autoSchedule,
                'schedule_frequency' => $this->scheduleFrequency,
                'description' => strip_tags($this->description), // Sanitize HTML
            ];

            // Create report record
            $report = Report::create([
                'team_id' => $team->id,
                'generated_by' => $user?->id,
                'title' => strip_tags($this->reportName), // Sanitize HTML
                'type' => $this->reportType,
                'format' => $this->format,
                'status' => 'pending',
                'filters' => $filters,
            ]);

            GenerateReportJob::dispatch($report);

            Log::info('User generated report', [
                'user_id' => $user?->id,
                'report_id' => $report->id,
                'report_type' => $this->reportType,
            ]);

            session()->flash('message', 'Report generation started. You will receive an email when ready.');

            // Use Livewire redirect without the `navigate` flag to avoid relying on Alpine.navigate
            $this->redirect(route('reports'));

            return;

        } catch (\Exception $e) {
            Log::error('Failed to generate report', [
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            $this->notify('Failed to generate report', 'error');
        }
    }

    public function selectAllMachines(): void
    {
        $machines = $this->getMachines();
        $this->selectedMachines = $machines->pluck('id')->toArray();
    }

    public function clearMachines(): void
    {
        $this->selectedMachines = [];
    }

    /**
     * @param  int|string  $machineId
     */
    public function toggleMachine($machineId): void
    {
        if (in_array($machineId, $this->selectedMachines)) {
            $this->selectedMachines = array_filter($this->selectedMachines, fn ($id) => $id !== $machineId);
        } else {
            $this->selectedMachines[] = $machineId;
        }
    }

    public function render(): View
    {
        return view('livewire.report-generator', [
            'reportTypes' => $this->reportTypes,
            'machines' => $this->getMachines(),
            'geofences' => $this->getGeofences(),
        ]);
    }
}
