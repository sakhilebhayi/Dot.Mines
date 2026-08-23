<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

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
 */
class Permission extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'name',
        'display_name',
        'description',
        'group',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns this permission
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all roles with this permission
     */
    /** @return BelongsToMany<Role,$this,Pivot,'pivot'> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
