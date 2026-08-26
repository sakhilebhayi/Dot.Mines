<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\Machine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes machines that were never real.
 *
 * Seed data once reached production and left behind a machine with no
 * manufacturer id and a Faker name. It has no telemetry and belongs to no
 * fleet, but it does carry status='active', which is enough to hold the
 * guardian's quiet-hours check open every night: FleetActivity only treats
 * the fleet as idle when NO machine is active, so one phantom keeps the
 * whole fleet looking busy and turns overnight staleness into a warning.
 *
 * Deleting a machine cascades to roughly fifteen tables, so this reports
 * before it removes and refuses outright to delete anything that has
 * dependent rows. Inspection is the default; --confirm is the only way to
 * change data, and it still names the row explicitly.
 */
class PurgePhantomMachines extends Command
{
    protected $signature = 'machines:purge-phantom
                            {--audit : List the whole fleet read-only, so a phantom can be identified rather than guessed at}
                            {--id=* : Machine ids to remove; omit to inspect candidates only}
                            {--confirm : Actually delete. Without this the command only reports.}';

    protected $description = 'Report, and optionally remove, seed-artefact machines that were never real';

    /**
     * Resolved once. The schema does not change while the command runs, and
     * re-deriving it per machine meant introspecting every table in the
     * database for every row -- on a 26-machine fleet that was thousands of
     * information_schema queries and minutes of wall time.
     *
     * @var list<array{0: string, 1: string}>|null
     */
    private ?array $references = null;

    public function handle(): int
    {
        if ($this->option('audit')) {
            return $this->audit();
        }

        $candidates = $this->candidates();

        /** @var list<string> $requested */
        $requested = $this->option('id');

        if ($this->option('confirm') && $requested === []) {
            $this->error('--confirm requires --id. Refusing to delete a set the operator has not named.');

            return self::FAILURE;
        }

        // Validated before the empty-set shortcut below: an operator who
        // mistypes an id must hear about it, not read "nothing to do" and
        // conclude the deletion succeeded.
        if ($requested !== []) {
            $unknown = array_diff(array_map('intval', $requested), $candidates->pluck('id')->all());

            if ($unknown !== []) {
                $this->error('Not a phantom candidate, refusing: '.implode(', ', $unknown));

                return self::FAILURE;
            }
        }

        if ($candidates->isEmpty()) {
            $this->info('No phantom machines found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn('Phantom candidates (no manufacturer id, no integration -- nothing feeds them):');
        $this->line('');

        $rows = $candidates->map(function (Machine $machine): array {
            $dependents = $this->dependentsFor($machine);

            return [
                $machine->id,
                $machine->name,
                $machine->registration_number,
                $machine->status,
                $machine->created_at->toDateString(),
                $dependents->sum() === 0 ? 'none' : $dependents->filter()->map(
                    fn (int $count, string $table): string => "{$table}={$count}"
                )->implode(' '),
            ];
        });

        $this->table(['ID', 'Name', 'Registration', 'Status', 'Created', 'Dependent rows'], $rows->all());

        if (! $this->option('confirm')) {
            $this->line('');
            $this->info('Inspection only. Re-run with --confirm --id=<id> to delete.');

            return self::SUCCESS;
        }

        return $this->delete($candidates->whereIn('id', array_map('intval', $requested)));
    }

    /**
     * @param  Collection<int, Machine>  $targets
     */
    private function delete(Collection $targets): int
    {
        $deleted = 0;

        foreach ($targets as $machine) {
            $dependents = $this->dependentsFor($machine);

            if ($dependents->sum() > 0) {
                $this->error("Machine {$machine->id} has dependent rows; refusing (a cascade here would destroy real data).");

                return self::FAILURE;
            }

            DB::transaction(function () use ($machine, &$deleted): void {
                $integrationId = $machine->integration_id;
                $machine->delete();
                $deleted++;

                // machines_count is a stored count, not a live one, so a
                // direct delete would leave it overstating the fleet.
                if ($integrationId !== null) {
                    $integration = Integration::query()->find($integrationId);
                    $integration?->update(['machines_count' => $integration->machines()->count()]);
                }
            });

            $this->info("Deleted machine {$machine->id} ({$machine->name}).");
        }

        $this->line('');
        $this->info("Done. {$deleted} removed. Fleet now: ".Machine::query()->count()
            .' machines, '.Machine::query()->where('status', 'active')->count().' active.');

        return self::SUCCESS;
    }

    /**
     * The whole fleet, read-only, with the fields that distinguish a real
     * machine from a seed artefact.
     *
     * The candidate rules below are a guess until someone has looked: the
     * first version matched nothing in production because it assumed a
     * phantom would have no integration and no position, and the real row
     * did not fit. This exists so the rules can be set from the data.
     */
    private function audit(): int
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan needs it (larastan infers stdClass here)
         *
         * @phpstan-var Collection<int, Machine> $machines
         */
        $machines = Machine::query()->orderBy('id')->get();

        if ($machines->isEmpty()) {
            $this->info('No machines at all.');

            return self::SUCCESS;
        }

        $rows = $machines->map(fn (Machine $machine): array => [
            $machine->id,
            mb_strimwidth($machine->name, 0, 28, '…'),
            $machine->machine_type,
            $machine->manufacturer_id === null ? 'NULL' : ($machine->manufacturer_id === '' ? "''" : $machine->manufacturer_id),
            $machine->integration_id ?? '—',
            $machine->status,
            $machine->last_location_update?->toDateTimeString() ?? 'never',
            $this->dependentsFor($machine)->sum(),
        ]);

        $this->table(
            ['ID', 'Name', 'Type', 'Manufacturer id', 'Integr.', 'Status', 'Last seen', 'Dep. rows'],
            $rows->all(),
        );

        $this->line('');
        $this->info('Fleet: '.$machines->count().' machines, '
            .$machines->where('status', 'active')->count().' active. Read-only; nothing changed.');

        return self::SUCCESS;
    }

    /**
     * A machine no manufacturer claims and no integration owns.
     *
     * The first version also required that it had never reported a position,
     * and that is why it matched nothing: the production phantom carries a
     * stale position from the day the seed ran. Absence of a position was
     * never the right test anyway -- what makes a machine real is that
     * something is feeding it, and the phantom is fed by nothing.
     *
     * Candidacy is deliberately broad and cheap; the load-bearing guard is
     * the dependent-row check at delete time, which refuses any machine that
     * has accumulated history. A real machine cannot avoid accumulating it.
     * Deletion additionally requires the operator to name the id, having
     * read --audit first.
     *
     * @return Collection<int, Machine>
     */
    private function candidates(): Collection
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan needs it (larastan infers stdClass here)
         *
         * @phpstan-var Collection<int, Machine> $machines
         */
        $machines = Machine::query()
            ->where(fn ($query) => $query->whereNull('manufacturer_id')->orWhere('manufacturer_id', ''))
            ->whereNull('integration_id')
            ->get();

        return $machines;
    }

    /**
     * Every row in every table that points at this machine, discovered from
     * the schema rather than a hand-kept list, so a table added later cannot
     * silently fall outside the safety check.
     *
     * @return Collection<string, int>
     */
    private function dependentsFor(Machine $machine): Collection
    {
        /** @var Collection<string, int> $counts */
        $counts = collect();

        $this->references ??= $this->machineReferences();

        foreach ($this->references as [$table, $column]) {
            $count = DB::table($table)->where($column, $machine->id)->count();

            if ($count > 0) {
                $counts->put("{$table}.{$column}", $count);
            }
        }

        return $counts;
    }

    /**
     * Every (table, column) pair whose foreign key points at machines.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function machineReferences(): array
    {
        $references = [];

        /** @var list<string> $listing */
        $listing = Schema::getTableListing();

        foreach ($listing as $listed) {
            $table = $this->bareName($listed);

            if ($table === 'machines') {
                continue;
            }

            /** @var list<array{columns: list<string>, foreign_table: string}> $foreignKeys */
            $foreignKeys = Schema::getForeignKeys($table);

            foreach ($foreignKeys as $foreignKey) {
                if ($this->bareName($foreignKey['foreign_table']) !== 'machines') {
                    continue;
                }

                $column = $foreignKey['columns'][0] ?? null;

                if (is_string($column)) {
                    $references[] = [$table, $column];
                }
            }
        }

        return $references;
    }

    /** Postgres lists tables schema-qualified; the app refers to them bare. */
    private function bareName(string $name): string
    {
        return str_contains($name, '.') ? explode('.', $name)[1] : $name;
    }
}
