<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PlatformErrorLog Model
 *
 * Stores every unhandled exception and logged error in the application.
 * Stack traces are never exposed to end-users — only the `error_id` UUID
 * is surfaced so users can report issues without revealing internals.
 *
 * @property int $id
 * @property string $error_id UUID — safe to show to users
 * @property string $level error|warning|critical|info
 * @property string $category app|api|integration|queue|frontend
 * @property string|null $http_method
 * @property string|null $url
 * @property string|null $route_name
 * @property int|null $http_status
 * @property string|null $exception_class
 * @property string $message
 * @property string|null $stack_trace
 * @property array<string, mixed>|null $context
 * @property string|null $user_id
 * @property int|null $team_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $environment
 * @property string|null $app_version
 * @property bool $resolved
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Team|null $team
 */
class PlatformErrorLog extends Model
{
    protected $fillable = [
        'error_id',
        'level',
        'category',
        'http_method',
        'url',
        'route_name',
        'http_status',
        'exception_class',
        'message',
        'stack_trace',
        'context',
        'user_id',
        'team_id',
        'ip_address',
        'user_agent',
        'environment',
        'app_version',
        'resolved',
        'resolved_at',
    ];

    /**
     * Fields that must never be serialised to JSON responses.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'stack_trace',  // internals — admin-only
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope to unresolved errors only.
     *
     * @param  Builder<PlatformErrorLog>  $query
     * @return Builder<PlatformErrorLog>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }

    /**
     * Scope to a specific level.
     *
     * @param  Builder<PlatformErrorLog>  $query
     * @return Builder<PlatformErrorLog>
     */
    public function scopeOfLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }
}
