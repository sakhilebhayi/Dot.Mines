<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A deletion marker for incremental sync: when a synced entity is deleted
 * (hard or soft), a tombstone tells clients to evict the cached row. Written
 * exclusively by the HasSyncVersion trait; read exclusively by SyncController,
 * always filtered by team_id.
 *
 * @property int $id
 * @property int $team_id
 * @property string $entity_type
 * @property int $entity_id
 * @property int $sync_version
 * @property Carbon|null $created_at
 */
class SyncTombstone extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string> */
    protected $guarded = [];
}
