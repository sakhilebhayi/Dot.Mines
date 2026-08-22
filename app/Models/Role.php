<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Role Model
 *
 * Represents user roles within a team (Admin, Fleet Manager, Operator, Viewer)
 * Used for role-based access control throughout the application
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'name',
        'display_name',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns this role
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all permissions for this role
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Check if role has a permission
     */
    public function hasPermission($permission)
    {
        return $this->permissions()->where('name', $permission)->exists();
    }
}
