<?php

namespace App\Traits;

use App\Models\SyncTombstone;
use App\Services\Sync\SyncSequence;
use Illuminate\Database\Eloquent\Model;

/**
 * Stamps a monotonic sync_version on every change and writes a tombstone on
 * every deletion, making the model consumable by the incremental sync API.
 * Soft deletes also tombstone: the UI excludes trashed rows, so clients must
 * evict them either way.
 */
trait HasSyncVersion
{
    public static function bootHasSyncVersion(): void
    {
        static::saving(function (Model $model): void {
            if (! $model->exists || $model->isDirty()) {
                $model->setAttribute('sync_version', SyncSequence::next());
            }
        });

        static::deleted(function (Model $model): void {
            $teamId = $model->getAttribute('team_id');

            if ($teamId === null) {
                return;
            }

            SyncTombstone::create([
                'team_id' => $teamId,
                'entity_type' => $model->getTable(),
                'entity_id' => $model->getKey(),
                'sync_version' => SyncSequence::next(),
                'created_at' => now(),
            ]);
        });
    }
}
