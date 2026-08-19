<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Notification Model
 *
 * @property int $id
 * @property int $team_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property string $alert_level
 * @property array|null $data
 * @property string|null $action_url
 * @property bool $is_read
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Notification where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Notification whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Notification orderBy(string $column, string $direction = 'asc')
 * @method static Notification|null find(mixed $id, array $columns = ['*'])
 * @method static Notification findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'type',
        'title',
        'message',
        'alert_level',
        'data',
        'action_url',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'json',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function readBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_read')
            ->withPivot('read_at');
    }

    public function markAsRead($userId)
    {
        $this->readBy()->attach($userId);
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public function isCritical(): bool
    {
        return $this->alert_level === 'critical';
    }

    public function isUrgent(): bool
    {
        return in_array($this->alert_level, ['critical', 'high']);
    }
}
