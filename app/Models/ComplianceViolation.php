<?php

namespace App\Models;

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
 * @property \Carbon\Carbon $detected_at
 * @property \Carbon\Carbon $remediation_deadline
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ComplianceViolation where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ComplianceViolation whereIn(string $column, array<string|int> $values)
 * @method static ComplianceViolation|null find(mixed $id, array<string> $columns = ['*'])
 * @method static ComplianceViolation findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,ComplianceViolation> all(array<string> $columns = ['*'])
 */
class ComplianceViolation extends Model
{
    use HasFactory;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'remediation_deadline' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team that owns the violation.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who resolved the violation.
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
