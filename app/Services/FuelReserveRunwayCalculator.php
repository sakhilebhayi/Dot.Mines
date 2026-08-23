<?php

namespace App\Services;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Collection;

class FuelReserveRunwayCalculator
{
    private const TRAILING_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function calculate(): array
    {
        $currentReserves = (float) FuelTank::active()->sum('current_level_liters');

        $windowStart = now()->subDays(self::TRAILING_DAYS);
        $dispensing = FuelTransaction::where('transaction_type', 'dispensing')
            ->where('transaction_date', '>=', $windowStart)
            ->get();

        if (FuelTank::active()->count() === 0) {
            return $this->unavailable();
        }

        if ($dispensing->isEmpty()) {
            return $this->unavailable();
        }

        $distinctDays = $dispensing->map(fn (FuelTransaction $t) => $t->transaction_date?->format('Y-m-d'))->filter()->unique()->count();
        $daysSpanned = min(max(1, $distinctDays), self::TRAILING_DAYS);

        $totalDispensed = (float) $dispensing->sum('quantity_liters');
        $dailyConsumption = $totalDispensed / (float) $daysSpanned;

        $hasNoRecentConsumption = $dailyConsumption <= 0;
        $days = $hasNoRecentConsumption ? null : (int) round($currentReserves / $dailyConsumption);

        $basis = sprintf(
            'Active tank reserves divided by average daily dispensing volume over the trailing %d day(s) of data, as of %s.',
            $daysSpanned,
            now()->toDateString()
        );

        $whatIf = $this->computeWhatIf($dispensing, $dailyConsumption, $currentReserves, $daysSpanned);

        return [
            'available' => true,
            'reason' => null,
            'current_reserves_liters' => $currentReserves,
            'daily_consumption_liters' => $dailyConsumption,
            'days' => $days,
            'has_no_recent_consumption' => $hasNoRecentConsumption,
            'basis' => $basis,
            'what_if' => $whatIf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'reason' => 'insufficient_data',
            'current_reserves_liters' => null,
            'daily_consumption_liters' => null,
            'days' => null,
            'has_no_recent_consumption' => null,
            'basis' => null,
            'what_if' => null,
        ];
    }

    /**
     * @param  Collection<int, FuelTransaction>  $dispensing
     * @return array<string, mixed>|null
     */
    private function computeWhatIf($dispensing, float $dailyConsumption, float $currentReserves, int $daysSpanned): ?array
    {
        $topMachine = $dispensing->toBase()->whereNotNull('machine_id')
            ->groupBy('machine_id')
            ->map(fn ($group) => (float) $group->sum('quantity_liters'))
            ->sortDesc();

        if ($topMachine->isEmpty()) {
            return null;
        }

        $topMachineId = $topMachine->keys()->first();
        $machine = Machine::find($topMachineId);
        if (! $machine) {
            return null;
        }

        $machineDailyAvg = ((float) $topMachine->first()) / (float) $daysSpanned;
        $adjustedRate = $dailyConsumption - $machineDailyAvg;

        if ($adjustedRate <= 0) {
            return null;
        }

        return [
            'machine_name' => $machine->name,
            'days_without_machine' => (int) round($currentReserves / $adjustedRate),
        ];
    }
}
