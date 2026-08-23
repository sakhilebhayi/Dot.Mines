<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Carbon\Carbon;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Notification Model
 *
 * @property int $id
 * @property int $team_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property string $alert_level
 * @property array<string, mixed>|null $data
 * @property string|null $action_url
 * @property bool $is_read
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int|null $sync_version
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasSyncVersion;

    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'data' => 'json',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsToMany<User,$this,Pivot,'pivot'> */
    public function readBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_read')
            ->withPivot('read_at');
    }

    public function markAsRead(int $userId): void
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
