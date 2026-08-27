<?php

namespace App\Models;

use App\Support\ApiPayload;
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
 * @property array<string, mixed>|null $last_sync_stats
 * @property string|null $last_error
 * @property int $machines_count
 * @property array<string, mixed>|null $config
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Team|null $team
 * @property array<string, mixed>|null $capabilities
 * @property array<string, mixed>|null $sync_streams
 */
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory, HasTeamFilters;

    /** @var array<int, string> */
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
        'last_sync_stats',
        'last_sync_status', // success, failed
        'last_error',
        'last_error_at',
        'machines_count',
        'config', // JSON for provider-specific configuration
        'capabilities', // JSON list of data streams actually observed: fleet, telemetry, production, location
        'sync_streams', // JSON per-stream status: {status, last_synced_at, records} per capability key
    ];

    /** @var array<string> */
    protected $hidden = [
        'api_key',
        'api_secret',
        'webhook_secret',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_sync_stats' => 'array',
        'last_error_at' => 'datetime',
        // Real third-party API secrets live here (OAuth client secrets,
        // passwords, tokens) -- encrypted:json transparently encrypts on
        // write and decrypts on read, so every existing ->credentials array
        // access/assignment keeps working unchanged. See the
        // 2026_08_10_063135_encrypt_integration_credentials_at_rest
        // migration for the column-type change and backfill this required.
        'credentials' => 'encrypted:json',
        'config' => 'json',
        'capabilities' => 'json',
        'sync_streams' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team this integration belongs to
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all machines synced from this integration
     *
     * @return HasMany<Machine,$this>
     */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * True only if $key was actually derived from a real API response by
     * IntegrationService::deriveCapabilities() -- never assume a provider
     * supports a stream just because another provider does.
     */
    public function hasCapability(string $key): bool
    {
        return in_array($key, $this->capabilities ?? [], true);
    }

    /**
     * @return array{status: string, last_synced_at: ?string, records: int}|null
     */
    public function streamStatus(string $key): ?array
    {
        /** @psalm-suppress MixedAssignment */
        $stream = ($this->sync_streams ?? [])[$key] ?? null;

        /** @var array{status: string, last_synced_at: ?string, records: int}|null */
        return is_array($stream) ? $stream : null;
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
    public function markError(?string $error): bool
    {
        return $this->update([
            'last_error' => $error,
            'status' => 'error',
        ]);
    }

    /**
     * The provider's declared sync cadence in seconds (Bell 900, most
     * others 300 via the jobs default). Anything judging liveness or
     * freshness must measure against THIS, not a wall-clock guess -- a
     * 5-minute liveness rule against a 15-minute provider declared the
     * whole fleet offline between every sync.
     */
    public function syncIntervalSeconds(): int
    {
        $interval = ApiPayload::int(
            config("integrations.manufacturers.{$this->provider}.sync_interval"),
            0,
        );

        if ($interval > 0) {
            return $interval;
        }

        return ApiPayload::int(config('integrations.jobs.machines_sync_interval'), 300);
    }
}
