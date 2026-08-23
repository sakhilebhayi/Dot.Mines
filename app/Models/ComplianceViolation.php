<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ComplianceViolationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ComplianceViolation Model
 *
 * @property int $id
 * @property int $team_id
 * @property string $violation_type
 * @property string $description
 * @property string $severity
 * @property Carbon $detected_at
 * @property Carbon|null $remediation_deadline
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int|null $compliance_audit_id
 */
class ComplianceViolation extends Model
{
    /** @use HasFactory<ComplianceViolationFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'violation_type',
        'description',
        'severity',
        'detected_at',
        'remediation_deadline',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'detected_at' => 'datetime',
        'remediation_deadline' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns the violation.
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who resolved the violation.
     *
     * @return BelongsTo<User,$this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Check if violation is resolved.
     */
    public function isResolved(): bool
    {
        return ! is_null($this->resolved_at);
    }

    /**
     * Check if violation is overdue.
     */
    public function isOverdue(): bool
    {
        return ! $this->isResolved()
            && $this->remediation_deadline
            && $this->remediation_deadline->isPast();
    }
}
