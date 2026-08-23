<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A URL a team wants events delivered to.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property string $url
 * @property string|null $description
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property string|null $last_failure_reason
 * @property Carbon|null $auto_disabled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory, HasTeamFilters;

    /**
     * Consecutive failed deliveries before the endpoint switches itself off.
     *
     * Each delivery has already exhausted its own retries by the time it
     * counts here, so this is a receiver that has been unreachable for hours,
     * not a blip. Left running, a dead endpoint means every event queues a
     * doomed job forever.
     */
    public const FAILURES_BEFORE_AUTO_DISABLE = 15;

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'created_by',
        'url',
        'description',
        'secret',
        'events',
        'is_active',
    ];

    /**
     * The secret never appears in a response or a log line; it is shown to
     * the person who created the endpoint once, at creation, and never again.
     *
     * @var array<int, string>
     */
    protected $hidden = ['secret'];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'auto_disabled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<WebhookDelivery,$this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Whether this endpoint asked for the given event.
     *
     * A subscription of ["*"] opts in to events added later too, which is
     * what most integrators want and what the docs recommend.
     */
    public function wantsEvent(string $event): bool
    {
        return in_array('*', $this->events, true) || in_array($event, $this->events, true);
    }

    /**
     * Whether the URL, as stored, is still safe and reachable to send to.
     */
    public function isDeliverable(): bool
    {
        return $this->is_active;
    }
}
