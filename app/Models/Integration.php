<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Integration Model
 *
 * Represents a connection to a manufacturer API (Volvo, CAT, Komatsu, Bell, C-track)
 * Stores credentials and configuration for syncing data
 *
 * @property int $id
 * @property int $team_id
 * @property string $provider
 * @property string $name
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property array<string, mixed>|null $credentials
 * @property string|null $webhook_url
 * @property string|null $webhook_secret
 * @property string $status
 * @property Carbon|null $last_sync_at
 * @property string|null $last_sync_status
 * @property string|null $last_error
 * @property int $machines_count
 * @property array<string, mixed>|null $config
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'provider', // volvo, cat, komatsu, bell, c_track
        'name',
        'api_key',
        'api_secret',
        'credentials', // JSON for all credentials
        'webhook_url',
        'webhook_secret',
        'status', // connected, disconnected, error
        'last_sync_at',
        'last_sync_status', // success, failed
        'last_error',
        'machines_count',
        'config', // JSON for provider-specific configuration
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'webhook_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'credentials' => 'json',
            'config' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team this integration belongs to
     */
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all machines synced from this integration
     */
    /** @return HasMany<Machine, $this> */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * Check if integration is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Mark integration as synced
     */
    public function markSynced(): bool
    {
        return $this->update([
            'last_sync_at' => now(),
            'last_error' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Mark integration as errored
     */
    public function markError(string $error): bool
    {
        return $this->update([
            'last_error' => $error,
            'status' => 'error',
        ]);
    }
}
