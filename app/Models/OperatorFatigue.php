<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OperatorFatigue Model
 *
 * Tracks machine operator fatigue levels and work hours to ensure safety
 * and compliance with rest requirements.
 *
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property int|null $machine_id
 * @property Carbon $shift_date
 * @property string $shift_type
 * @property string $shift_start
 * @property string $shift_end
 * @property float $hours_worked
 * @property float $consecutive_days
 * @property int $fatigue_score
 * @property string $alert_level
 * @property float $break_time_minutes
 * @property int $incidents_count
 * @property bool $is_rested
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorFatigue where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorFatigue whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorFatigue orderBy(string $column, string $direction = 'asc')
 * @method static OperatorFatigue|null find(mixed $id, array $columns = ['*'])
 * @method static OperatorFatigue findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class OperatorFatigue extends Model
{
    use HasFactory;

    protected $table = 'operator_fatigue';

    protected $fillable = [
        'user_id',
        'team_id',
        'machine_id',
        'shift_date',
        'shift_type',
        'shift_start',
        'shift_end',
        'hours_worked',
        'consecutive_days',
        'fatigue_score',
        'alert_level',
        'break_time_minutes',
        'incidents_count',
        'is_rested',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'shift_start' => 'datetime:H:i',
        'shift_end' => 'datetime:H:i',
        'hours_worked' => 'float',
        'consecutive_days' => 'float',
        'fatigue_score' => 'integer',
        'break_time_minutes' => 'float',
        'incidents_count' => 'integer',
        'is_rested' => 'boolean',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user (operator) this fatigue record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team this fatigue record belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the machine this fatigue record is associated with.
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Calculate fatigue score based on various factors.
     */
    public function calculateFatigueScore(): int
    {
        $score = 0;

        // Hours worked contribution (0-30 points)
        if ($this->hours_worked > 12) {
            $score += 30;
        } elseif ($this->hours_worked > 10) {
            $score += 25;
        } elseif ($this->hours_worked > 8) {
            $score += 15;
        }

        // Consecutive days contribution (0-30 points)
        if ($this->consecutive_days >= 7) {
            $score += 30;
        } elseif ($this->consecutive_days >= 5) {
            $score += 20;
        } elseif ($this->consecutive_days >= 3) {
            $score += 10;
        }

        // Break time contribution (0-20 points) - inverse relationship
        if ($this->break_time_minutes < 30) {
            $score += 20;
        } elseif ($this->break_time_minutes < 60) {
            $score += 10;
        }

        // Night shift contribution (0-10 points)
        if ($this->shift_type === 'night') {
            $score += 10;
        }

        // Incidents contribution (0-10 points)
        $score += min($this->incidents_count * 5, 10);

        return min($score, 100);
    }

    /**
     * Determine alert level based on fatigue score.
     */
    public function determineAlertLevel(): string
    {
        $score = $this->fatigue_score;

        if ($score >= 80) {
            return 'critical';
        } elseif ($score >= 60) {
            return 'high';
        } elseif ($score >= 40) {
            return 'medium';
        } elseif ($score >= 20) {
            return 'low';
        }

        return 'none';
    }

    /**
     * Check if operator needs rest.
     */
    public function needsRest(): bool
    {
        return $this->fatigue_score >= 60 ||
               $this->consecutive_days >= 6 ||
               $this->hours_worked >= 12;
    }
}
