<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityLog Model
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $team_id
 * @property string $action
 * @property string|null $description
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'action',
        'description',
        'created_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
