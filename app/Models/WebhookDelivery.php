<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event, on its way to one endpoint, with what happened to it.
 *
 * @property int $id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon|null $delivered_at
 * @property Carbon|null $next_attempt_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * How long to wait before each retry, in seconds.
     *
     * The queue worker here runs `--tries=1` from a once-a-minute cron tick,
     * so a thrown exception would kill the delivery outright: retries are
     * scheduled by this job dispatching its own next attempt, not by the
     * worker. Five attempts spread over roughly two and a half hours covers
     * a deploy or a short outage on the receiving end without hammering it.
     *
     * @var array<int, int>
     */
    public const RETRY_DELAYS = [60, 300, 1800, 7200];

    public const MAX_ATTEMPTS = 5;

    /** @var array<int, string> */
    protected $fillable = [
        'webhook_endpoint_id',
        'event',
        'payload',
        'status',
        'attempts',
        'response_status',
        'error',
        'duration_ms',
        'delivered_at',
        'next_attempt_at',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint,$this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * Seconds to wait before the attempt after the one just made, or null
     * when the attempts are spent.
     */
    public function delayBeforeNextAttempt(): ?int
    {
        return self::RETRY_DELAYS[$this->attempts - 1] ?? null;
    }
}
