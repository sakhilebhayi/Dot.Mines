<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One entry in the Mine Operations Feed.
 *
 * System items are normalised from domain events; user items are posts
 * written by people. The feed never computes operational numbers -- a row
 * carries text, references, and the time the underlying event actually
 * happened.
 *
 * @property int $id
 * @property int $team_id
 * @property string $source
 * @property string $category
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $action_url
 * @property int|null $user_id
 * @property int|null $machine_id
 * @property int|null $operator_id
 * @property array<string, mixed>|null $data
 * @property string|null $dedupe_key
 * @property Carbon $occurred_at
 * @property Carbon|null $pinned_until
 * @property int|null $pinned_by
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Machine|null $machine
 * @property-read Operator|null $operator
 * @property-read Collection<int, FeedComment> $comments
 * @property-read Collection<int, FeedReaction> $reactions
 */
class FeedItem extends Model
{
    use HasTeamFilters, SoftDeletes;

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_USER = 'user';

    public const CATEGORY_FLEET = 'fleet';

    public const CATEGORY_PRODUCTION = 'production';

    public const CATEGORY_MAINTENANCE = 'maintenance';

    public const CATEGORY_ALERTS = 'alerts';

    public const CATEGORY_FUEL = 'fuel';

    public const CATEGORY_OPERATORS = 'operators';

    public const CATEGORY_ANNOUNCEMENT = 'announcement';

    /**
     * The closed reaction vocabulary: seen, done, needs attention.
     *
     * @var array<int, string>
     */
    public const REACTIONS = ['👍', '✅', '⚠️'];

    /**
     * Every category the feed understands, with its filter label.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_FLEET => 'Fleet',
        self::CATEGORY_PRODUCTION => 'Production',
        self::CATEGORY_MAINTENANCE => 'Maintenance',
        self::CATEGORY_ALERTS => 'Alerts',
        self::CATEGORY_FUEL => 'Fuel',
        self::CATEGORY_OPERATORS => 'Operators',
        self::CATEGORY_ANNOUNCEMENT => 'Announcements',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'source',
        'category',
        'type',
        'title',
        'body',
        'action_url',
        'user_id',
        'machine_id',
        'operator_id',
        'data',
        'dedupe_key',
        'occurred_at',
        'pinned_until',
        'pinned_by',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'occurred_at' => 'datetime',
            'pinned_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Machine,$this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<Operator,$this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return HasMany<FeedComment,$this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    /**
     * @return HasMany<FeedReaction,$this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(FeedReaction::class);
    }

    public function isPinned(): bool
    {
        return $this->pinned_until !== null && $this->pinned_until->isFuture();
    }

    public function isSystem(): bool
    {
        return $this->source === self::SOURCE_SYSTEM;
    }
}
