<?php

namespace App\Services\DataQuality;

use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Checks the team's own real, already-stored data for the concrete issue
 * classes this app can actually verify without any external API: missing
 * values, impossible values, invalid/future timestamps, duplicate records,
 * unit inconsistencies, and stale telemetry. Deliberately does not attempt
 * anything that would require a real manufacturer connection (out-of-order
 * event reconciliation, API-gap detection) -- see wiki.md §5.1 for why none
 * of that exists yet.
 *
 * Never silently "fixes" or drops a suspect value -- every finding is
 * reported with the record it came from so a human decides what to do.
 */
class DataQualityService
{
    /**
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function checkTeam(Team $team): array
    {
        return [
            'production_records' => $this->checkProductionRecords($team),
            'fuel_transactions' => $this->checkFuelTransactions($team),
            'machines' => $this->checkMachines($team),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function checkProductionRecords(Team $team): Collection
    {
        $issues = collect();

        ProductionRecord::where('team_id', $team->id)
            ->where(function ($q) {
                $q->whereNull('quantity_produced')
                    ->orWhere('quantity_produced', '<', 0)
                    ->orWhere('record_date', '>', now()->addDay());
            })
            ->get()
            ->each(function (ProductionRecord $record) use ($issues) {
                if ((float) $record->quantity_produced < 0) {
                    $issues->push($this->finding($record, 'impossible_value', 'quantity_produced is negative ('.((string) $record->quantity_produced).')'));
                }

                if ($record->record_date->greaterThan(now()->addDay())) {
                    $issues->push($this->finding($record, 'invalid_timestamp', "record_date ({$record->record_date->toDateString()}) is in the future"));
                }
            });

        // Duplicates: same machine logging production for the same shift on
        // the same date more than once.
        ProductionRecord::where('team_id', $team->id)
            ->whereNotNull('machine_id')
            ->selectRaw('machine_id, record_date, shift, COUNT(*) as total, MIN(id) as first_id')
            ->groupBy('machine_id', 'record_date', 'shift')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (ProductionRecord $group) use ($issues) {
                $record = ProductionRecord::find((int) $group->getAttribute('first_id'));
                if ($record) {
                    $issues->push($this->finding(
                        $record,
                        'duplicate',
                        ((string) $group->getAttribute('total')).' production records exist for the same machine, date, and shift'
                    ));
                }
            });

        return $issues->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function checkFuelTransactions(Team $team): Collection
    {
        $issues = collect();

        FuelTransaction::where('team_id', $team->id)
            ->get()
            ->each(function (FuelTransaction $transaction) use ($issues) {
                if ($transaction->quantity_liters !== null && $transaction->quantity_liters <= 0) {
                    $issues->push($this->finding($transaction, 'impossible_value', "quantity_liters is not positive ({$transaction->quantity_liters})"));
                }

                if ($transaction->unit_price !== null && $transaction->unit_price < 0) {
                    $issues->push($this->finding($transaction, 'impossible_value', "unit_price is negative ({$transaction->unit_price})"));
                }

                if ($transaction->transaction_date && Carbon::parse($transaction->transaction_date)->greaterThan(now()->addDay())) {
                    $issues->push($this->finding($transaction, 'invalid_timestamp', 'transaction_date is in the future'));
                }

                if ($transaction->unit_price !== null && $transaction->quantity_liters !== null && $transaction->total_cost !== null) {
                    $expected = round((float) $transaction->unit_price * (float) $transaction->quantity_liters, 2);
                    $actual = round((float) $transaction->total_cost, 2);

                    if (abs($expected - $actual) > 0.05) {
                        $issues->push($this->finding(
                            $transaction,
                            'unit_inconsistency',
                            "total_cost ({$actual}) doesn't match unit_price × quantity_liters ({$expected})"
                        ));
                    }
                }
            });

        return $issues->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function checkMachines(Team $team): Collection
    {
        $issues = collect();

        Machine::where('team_id', $team->id)
            ->where('status', 'active')
            ->get()
            ->each(function (Machine $machine) use ($issues) {
                if (! $machine->last_location_update) {
                    $issues->push($this->finding($machine, 'missing_data', 'status is active but last_location_update has never been set'));

                    return;
                }

                $staleness = Carbon::parse($machine->last_location_update)->diffInHours(now());

                if ($staleness > 24) {
                    $issues->push($this->finding(
                        $machine,
                        'stale_telemetry',
                        "status is active but last location update was {$staleness}h ago"
                    ));
                }
            });

        return $issues->values();
    }

    /**
     * @param  Model  $record
     * @return array<string, mixed>
     */
    private function finding($record, string $category, string $description): array
    {
        return [
            'category' => $category,
            'model' => get_class($record),
            'id' => $record->id,
            'description' => $description,
        ];
    }
}
