<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FuelBudget Model
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $mine_area_id
 * @property string $period_type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property float $budgeted_amount
 * @property float $budgeted_liters
 * @property float|null $actual_spent
 * @property float|null $actual_liters
 * @property string $status
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read float $budget_utilization
 */
class FuelBudget extends Model
{
    use HasTeamFilters;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'mine_area_id',
        'period_type',
        'start_date',
        'end_date',
        'budgeted_amount',
        'budgeted_liters',
        'actual_spent',
        'actual_liters',
        'status',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budgeted_amount' => 'decimal:2',
        'budgeted_liters' => 'decimal:2',
        'actual_spent' => 'decimal:2',
        'actual_liters' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get budget utilization percentage (money)
     */
    protected function getBudgetUtilizationAttribute(): float
    {
        if ($this->budgeted_amount == 0) {
            return 0;
        }

        return round(($this->actual_spent / $this->budgeted_amount) * 100, 2);
    }

    /**
     * Get remaining budget
     */
    protected function getRemainingBudgetAttribute(): float
    {
        return $this->budgeted_amount - $this->actual_spent;
    }

    /**
     * Get volume utilization percentage
     */
    protected function getVolumeUtilizationAttribute(): ?float
    {
        if (! $this->budgeted_liters || $this->budgeted_liters == 0) {
            return null;
        }

        return round(($this->actual_liters / $this->budgeted_liters) * 100, 2);
    }

    /**
     * Check if budget is exceeded
     */
    public function isExceeded(): bool
    {
        return $this->actual_spent > $this->budgeted_amount;
    }

    /**
     * Check if budget is near limit (>90%)
     */
    public function isNearLimit(): bool
    {
        return $this->budget_utilization >= 90 && $this->budget_utilization < 100;
    }

    /**
     * Scope for active budgets
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for exceeded budgets
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExceeded($query)
    {
        $query->whereRaw('actual_spent > budgeted_amount');

        return $query;
    }

    /**
     * Scope for current period
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrent($query)
    {
        $now = now();

        return $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }
}
