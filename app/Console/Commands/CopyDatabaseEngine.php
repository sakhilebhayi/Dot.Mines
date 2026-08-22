<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies every application table from one database connection to another,
 * then verifies the copy (hybrid architecture spec, Slice 0). Built for the
 * SQLite -> MySQL authority migration but connection-agnostic, which is also
 * how CI exercises it (sqlite -> sqlite).
 *
 * The schema is always created by running the migrations against the target
 * -- never inferred from the source -- so the target ends up with the
 * canonical column types for its engine. The source is strictly read-only
 * throughout, which is what makes the production cutover trivially
 * reversible: reverting is a one-line .env change back to sqlite.
 */
class CopyDatabaseEngine extends Command
{
    protected $signature = 'db:engine-copy
        {--from= : Source connection name (read-only throughout)}
        {--to= : Target connection name}
        {--fresh : Wipe the target schema first (migrate:fresh)}
        {--verify-only : Skip migration and copy; only compare source and target}
        {--skip-missing : Copy only columns the target schema has, loudly listing every exclusion (for sources with legacy drift)}';

    protected $description = 'Copy all application data between database connections with per-table verification';

    /** Transient tables that must not be copied; `migrations` is owned by the target's own migrate run. */
    private const EXCLUDED_TABLES = ['migrations', 'cache', 'cache_locks'];

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $fromOpt = $this->option('from');
        $toOpt = $this->option('to');
        $from = is_string($fromOpt) ? $fromOpt : '';
        $to = is_string($toOpt) ? $toOpt : '';

        if ($from === '' || $to === '' || $from === $to) {
            $this->error('Provide distinct --from and --to connection names.');

            return self::FAILURE;
        }

        foreach ([$from, $to] as $connection) {
            if (config("database.connections.{$connection}") === null) {
                $this->error("Connection [{$connection}] is not configured.");

                return self::FAILURE;
            }
        }

        $tables = $this->copyableTables($from);

        if ($tables === []) {
            $this->error("Source connection [{$from}] has no application tables.");

            return self::FAILURE;
        }

        if (! $this->option('verify-only')) {
            if (! $this->prepareTarget($to)) {
                return self::FAILURE;
            }

            try {
                $this->copyTables($from, $to, $tables);
            } catch (\RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        return $this->verify($from, $to, $tables) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function copyableTables(string $connection): array
    {
        $names = array_column(Schema::connection($connection)->getTables(), 'name');

        $names = array_filter($names, fn (string $name): bool => ! in_array($name, self::EXCLUDED_TABLES, true)
            && ! str_starts_with($name, 'sqlite_'));

        sort($names);

        return $names;
    }

    private function prepareTarget(string $to): bool
    {
        if ($this->option('fresh')) {
            $this->info("Running migrate:fresh on [{$to}]...");
            Artisan::call('migrate:fresh', ['--database' => $to, '--force' => true], $this->output);

            return true;
        }

        // Guard against pointing --to at a live database: checked BEFORE
        // migrating, because some migrations seed rows (subscription_plans)
        // and would make an empty target look populated.
        if ($this->targetHasData($to)) {
            $this->error("Target [{$to}] already contains data. Re-run with --fresh to wipe it first.");

            return false;
        }

        $this->info("Running migrate on [{$to}]...");
        Artisan::call('migrate', ['--database' => $to, '--force' => true], $this->output);

        return true;
    }

    private function targetHasData(string $to): bool
    {
        foreach ($this->copyableTables($to) as $table) {
            if (DB::connection($to)->table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tables
     */
    private function copyTables(string $from, string $to, array $tables): void
    {
        Schema::connection($to)->disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (! Schema::connection($to)->hasTable($table)) {
                    $this->warn("Skipping table [{$table}]: not present on target (legacy schema drift?).");

                    continue;
                }

                $sourceColumns = Schema::connection($from)->getColumnListing($table);
                $targetColumns = Schema::connection($to)->getColumnListing($table);
                $sharedColumns = array_values(array_intersect($sourceColumns, $targetColumns));
                $extraColumns = array_values(array_diff($sourceColumns, $targetColumns));

                if ($extraColumns !== []) {
                    if ($this->option('skip-missing') !== true) {
                        throw new \RuntimeException(
                            "Source table [{$table}] has columns the target lacks: "
                            .implode(', ', $extraColumns)
                            .'. Re-run with --skip-missing to exclude them, or reconcile the schema.',
                        );
                    }

                    $this->warn("  [{$table}] excluding source-only columns: ".implode(', ', $extraColumns));
                }

                // Migration-seeded rows (e.g. subscription_plans) must not
                // survive: the source is authoritative for every copied table.
                DB::connection($to)->table($table)->delete();

                $copied = 0;
                $orderColumn = in_array('id', $sharedColumns, true) ? 'id' : ($sharedColumns[0] ?? 'rowid');

                DB::connection($from)->table($table)->select($sharedColumns)->orderBy($orderColumn)->chunk(
                    self::CHUNK_SIZE,
                    function ($rows) use ($to, $table, &$copied): void {
                        $payload = $rows->map(fn (object $row): array => (array) $row)->all();
                        DB::connection($to)->table($table)->insert($payload);
                        $copied += count($payload);
                    },
                );

                $this->line(sprintf('  %-40s %6d rows', $table, $copied));
            }
        } finally {
            Schema::connection($to)->enableForeignKeyConstraints();
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function verify(string $from, string $to, array $tables): bool
    {
        $this->info('Verifying...');
        $failures = 0;

        foreach ($tables as $table) {
            if (! Schema::connection($to)->hasTable($table)) {
                continue;
            }

            $problems = [];

            $sourceCount = DB::connection($from)->table($table)->count();
            $targetCount = DB::connection($to)->table($table)->count();

            if ($sourceCount !== $targetCount) {
                $problems[] = "rows {$sourceCount} vs {$targetCount}";
            }

            if ($this->hasIntegerId($from, $table)) {
                $sourceSum = (int) DB::connection($from)->table($table)->sum('id');
                $targetSum = (int) DB::connection($to)->table($table)->sum('id');

                if ($sourceSum !== $targetSum) {
                    $problems[] = "sum(id) {$sourceSum} vs {$targetSum}";
                }
            }

            if ($problems === []) {
                $this->line(sprintf('  %-40s ok (%d rows)', $table, $targetCount));
            } else {
                $failures++;
                $this->error(sprintf('  %-40s MISMATCH: %s', $table, implode(', ', $problems)));
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} table(s) failed verification. The source was not modified.");

            return false;
        }

        $this->info('All tables verified.');

        return true;
    }

    /**
     * sum(id) is only a meaningful checksum for numeric ids; string ids
     * (e.g. job_batches) make pgsql's SUM() throw outright.
     */
    private function hasIntegerId(string $connection, string $table): bool
    {
        if (! Schema::connection($connection)->hasColumn($table, 'id')) {
            return false;
        }

        $type = Schema::connection($connection)->getColumnType($table, 'id');

        return str_contains(strtolower($type), 'int');
    }
}
