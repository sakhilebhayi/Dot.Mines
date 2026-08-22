<?php

namespace App\Console\Commands;

use App\Services\ShiftService;
use Illuminate\Console\Command;

class PerformShiftChange extends Command
{
    protected $signature = 'shift:change {team_id} {shift_type=day} {--default-mine-area=}';

    protected $description = 'Perform shift change: snapshot assignments and reset fleet for next shift.';

    public function handle(ShiftService $shiftService): int
    {
        $teamId = (int) $this->argument('team_id');
        $rawShiftType = $this->argument('shift_type');
        $shiftType = is_string($rawShiftType) ? $rawShiftType : 'day';
        $defaultMineArea = $this->option('default-mine-area');

        $this->info("Starting shift change for team {$teamId}, shift={$shiftType}");

        $shift = $shiftService->performShiftChange($teamId, $shiftType, ($defaultMineArea !== null && $defaultMineArea !== '' && $defaultMineArea !== '0') ? (int) $defaultMineArea : null);

        $this->info("Shift record created: {$shift->id}");

        return 0;
    }
}
