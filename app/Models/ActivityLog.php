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
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ActivityLog where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ActivityLog whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|ActivityLog orderBy(string $column, string $direction = 'asc')
 * @method static ActivityLog|null find(mixed $id, array $columns = ['*'])
 * @method static ActivityLog findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
