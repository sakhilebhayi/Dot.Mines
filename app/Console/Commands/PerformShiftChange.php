<?php

namespace App\Console\Commands;

use App\Services\ShiftService;
use Illuminate\Console\Command;

class PerformShiftChange extends Command
{
    protected $signature = 'shift:change {team_id} {shift_type=day} {--default-mine-area=}';

    protected $description = 'Perform shift change: snapshot assignments and reset fleet for next shift.';

    public function handle(ShiftService $shiftService)
    {
        $teamId = (int) $this->argument('team_id');
        $shiftType = $this->argument('shift_type') ?? 'day';
        $defaultMineArea = $this->option('default-mine-area');

        $this->info("Starting shift change for team {$teamId}, shift={$shiftType}");

        $shift = $shiftService->performShiftChange($teamId, $shiftType, $defaultMineArea ? (int) $defaultMineArea : null);

        $this->info("Shift record created: {$shift->id}");

        return 0;
    }
}
