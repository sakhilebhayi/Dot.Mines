<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeedAuditLog
 *
 * Immutable log of admin actions on feed content.
 *
 * @property int $id
 * @property int $team_id
 * @property int $actor_id
 * @property string $action pin|unpin|admin_delete|override_approval|invite_sent|go_live_set|bulk_approve|bulk_reject|export|settings_changed
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, mixed>|null $meta
 * @property string|null $ip_address
 * @property \Carbon\Carbon $created_at
 */
class FeedAuditLog extends Model
{
    public $timestamps = false;

    public const ACTIONS = [
        'pin' => 'Pinned post',
        'unpin' => 'Unpinned post',
        'admin_delete' => 'Admin deleted post',
        'override_approval' => 'Overrode approval',
        'invite_sent' => 'Sent onboarding invite',
        'go_live_set' => 'Set go-live date',
        'bulk_approve' => 'Bulk approved posts',
        'bulk_reject' => 'Bulk rejected posts',
        'export' => 'Exported feed data',
        'settings_changed' => 'Changed feed settings',
    ];

    protected $fillable = [
        'team_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function record(string $action, Model $subject, ?array $meta = null): static
    {
        $ip = null;
        if (! app()->runningInConsole()) {
            try {
                $ip = request()->ip();
            } catch (\Throwable) {
                //
            }
        }

        return static::create([
            'team_id' => auth()->user()->current_team_id,
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'meta' => $meta,
            'ip_address' => $ip,
        ]);
    }
}
