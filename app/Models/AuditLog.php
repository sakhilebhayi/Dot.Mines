<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-wide append-only audit log.
 *
 * Covers authentication events, feed actions, fleet changes,
 * maintenance operations, and subscription lifecycle events.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $description
 * @property string|null $ip_address
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $meta
 * @property \Carbon\Carbon $created_at
 */
class AuditLog extends Model
{
    /** Append-only — no updated_at column */
    public const UPDATED_AT = null;

    // ── Action constants ──────────────────────────────────────────────────────

    // Authentication
    public const LOGIN_SUCCESS = 'auth.login.success';

    public const LOGIN_FAILED = 'auth.login.failed';

    public const LOGIN_LOCKOUT = 'auth.login.lockout';

    public const LOGOUT = 'auth.logout';

    public const TEAM_SWITCH = 'auth.team.switch';

    // Feed posts
    public const FEED_POST_CREATED = 'feed.post.created';

    public const FEED_POST_DELETED = 'feed.post.deleted';

    public const FEED_POST_APPROVED = 'feed.post.approved';

    public const FEED_POST_REJECTED = 'feed.post.rejected';

    // Feed attachments
    public const FEED_ATTACHMENT_UPLOAD = 'feed.attachment.upload';

    public const FEED_ATTACHMENT_DELETED = 'feed.attachment.deleted';

    // Fleet (machines)
    public const MACHINE_CREATED = 'fleet.machine.created';

    public const MACHINE_UPDATED = 'fleet.machine.updated';

    public const MACHINE_DELETED = 'fleet.machine.deleted';

    // Maintenance records
    public const MAINTENANCE_CREATED = 'maintenance.record.created';

    public const MAINTENANCE_UPDATED = 'maintenance.record.updated';

    public const MAINTENANCE_COMPLETED = 'maintenance.record.completed';

    public const MAINTENANCE_DELETED = 'maintenance.record.deleted';

    // Report lifecycle
    public const REPORT_GENERATED = 'report.generated';

    public const REPORT_DELETED = 'report.deleted';

    public const REPORT_DOWNLOAD = 'report.download';

    // File uploads / deletions
    public const FILE_UPLOADED = 'file.uploaded';

    public const FILE_DELETED = 'file.deleted';

    // Admin actions
    public const ADMIN_USER_ROLE_CHANGED = 'admin.user.role_changed';

    public const ADMIN_USER_REMOVED = 'admin.user.removed';

    public const ADMIN_TEAM_SETTINGS = 'admin.team.settings_changed';

    public const ADMIN_INTEGRATION_SYNC = 'admin.integration.synced';

    public const ADMIN_IMPORT = 'admin.data.imported';

    // Subscriptions
    public const SUBSCRIPTION_CREATED = 'subscription.created';

    public const SUBSCRIPTION_UPDATED = 'subscription.updated';

    public const SUBSCRIPTION_CANCELLED = 'subscription.cancelled';

    // ── Model config ──────────────────────────────────────────────────────────

    protected $fillable = [
        'team_id',
        'actor_id',
        'action',
        'description',
        'ip_address',
        'subject_type',
        'subject_id',
        'meta',
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

    // ── Relations ─────────────────────────────────────────────────────────────

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
