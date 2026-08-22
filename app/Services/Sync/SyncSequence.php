<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Monotonic global version sequence for incremental sync (hybrid spec §7/§19).
 *
 * Each next() inserts a row into `sync_versions` and uses the auto-increment
 * id as the version: atomic under concurrency and portable across every
 * engine this app touches (sqlite tests, pgsql dev, mysql prod). Old rows are
 * pruned by the scheduler; the sequence itself never rewinds because
 * auto-increment counters survive deletes on all three engines.
 */
class SyncSequence
{
    public static function next(): int
    {
        return DB::table('sync_versions')->insertGetId(['created_at' => now()]);
    }

    public static function current(): int
    {
        return (int) DB::table('sync_versions')->max('id');
    }

    /**
     * Keeps the sequence table small; retains a tail so current() stays
     * meaningful even right after pruning.
     */
    public static function prune(int $keep = 1000): void
    {
        DB::table('sync_versions')->where('id', '<', self::current() - $keep)->delete();
    }
}
