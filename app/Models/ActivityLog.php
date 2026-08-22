<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * ActivityLog Model
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $team_id
 * @property string $action
 * @property string|null $description
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'team_id',
        'action',
        'description',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
