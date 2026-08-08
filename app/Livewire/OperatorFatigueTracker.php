<?php

namespace App\Livewire;

use App\Models\OperatorFatigue;
use App\Models\Team;
use App\Models\User;
use App\Services\OperatorFatigueService;
use App\Traits\BrowserEventBridge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OperatorFatigueTracker extends Component
{
    use BrowserEventBridge;

    public int $operatorId;

    public string $shiftDate;

    public string $shiftType = 'morning';

    public string $shiftStart = '06:00';

    public string $shiftEnd = '14:00';

    public float $hoursWorked = 8;

    public float $consecutiveDays = 1;

    public float $breakTimeMinutes = 60;

    public int $incidentsCount = 0;

    public ?string $notes = null;

    public function mount(): void
    {
        // Same null-currentTeam case documented on Dashboard::mount() and
        // ReportController::view2() -- EnsureTeamContext already redirects
        // a genuinely teamless user before this ever mounts, but keep the
        // guard here too since this component can be embedded elsewhere.
        if (! $this->resolveCurrentTeam()) {
            $this->redirect(route('teams.create'), navigate: true);

            return;
        }

        $this->operatorId = Auth::id();
        $this->shiftDate = now()->toDateString();
    }

    private function resolveCurrentTeam(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    /**
     * @return Collection<int, User>
     */
    public function getOperatorsProperty()
    {
        return $this->resolveCurrentTeam()?->allUsers() ?? collect();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, OperatorFatigue>
     */
    public function getRosterProperty()
    {
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            return collect();
        }

        return OperatorFatigue::where('team_id', $team->id)
            ->with('user')
            ->orderByDesc('shift_date')
            ->orderByDesc('fatigue_score')
            ->limit(25)
            ->get();
    }

    public function submitShift(OperatorFatigueService $service): void
    {
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            $this->redirect(route('teams.create'), navigate: true);

            return;
        }

        $validated = $this->validate([
            'operatorId' => 'required|integer|exists:users,id',
            'shiftDate' => 'required|date',
            'shiftType' => 'required|in:morning,afternoon,night',
            'shiftStart' => 'required|date_format:H:i',
            'shiftEnd' => 'required|date_format:H:i',
            'hoursWorked' => 'required|numeric|min:0|max:24',
            'consecutiveDays' => 'required|numeric|min:0|max:31',
            'breakTimeMinutes' => 'required|numeric|min:0|max:1440',
            'incidentsCount' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $operator = $team->allUsers()->firstWhere('id', (int) $validated['operatorId']);

        if (! $operator) {
            $this->addError('operatorId', 'That operator is not a member of this team.');

            return;
        }

        $fatigue = $service->recordShift($operator, $team, [
            'shift_date' => $validated['shiftDate'],
            'shift_type' => $validated['shiftType'],
            'shift_start' => $validated['shiftStart'],
            'shift_end' => $validated['shiftEnd'],
            'hours_worked' => $validated['hoursWorked'],
            'consecutive_days' => $validated['consecutiveDays'],
            'break_time_minutes' => $validated['breakTimeMinutes'],
            'incidents_count' => $validated['incidentsCount'],
            'notes' => $validated['notes'],
        ]);

        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => "Fatigue score recorded: {$fatigue->fatigue_score}/100 ({$fatigue->alert_level})."]);

        $this->reset(['incidentsCount', 'notes']);
        $this->breakTimeMinutes = 60;
    }

    public function render()
    {
        return view('livewire.operator-fatigue-tracker');
    }
}
