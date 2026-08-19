<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission Model
 *
 * Represents granular permissions that can be assigned to roles
 * Used for fine-grained authorization control
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string|null $group
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Permission where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission orderBy(string $column, string $direction = 'asc')
 * @method static Permission|null find(mixed $id, array $columns = ['*'])
 * @method static Permission findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'display_name',
        'description',
        'group',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns this permission
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all roles with this permission
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
