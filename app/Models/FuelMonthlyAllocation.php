<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * FuelMonthlyAllocation Model
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $mine_area_id
 * @property int $year
 * @property int $month
 * @property float $allocated_liters
 * @property float $fuel_price_per_liter
 * @property float $total_budget_zar
 * @property float $consumed_liters
 * @property float $remaining_liters
 * @property float $spent_zar
 * @property float $remaining_budget_zar
 * @property string $status
 * @property string|null $notes
 * @property float $consumption_percentage
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FuelMonthlyAllocation extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'mine_area_id',
        'year',
        'month',
        'allocated_liters',
        'fuel_price_per_liter',
        'total_budget_zar',
        'consumed_liters',
        'remaining_liters',
        'spent_zar',
        'remaining_budget_zar',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'allocated_liters' => 'decimal:2',
            'fuel_price_per_liter' => 'decimal:2',
            'total_budget_zar' => 'decimal:2',
            'consumed_liters' => 'decimal:2',
            'remaining_liters' => 'decimal:2',
            'spent_zar' => 'decimal:2',
            'remaining_budget_zar' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<FuelTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class, 'monthly_allocation_id');
    }

    /**
     * Get the period name (e.g., "January 2026")
     */
    public function getPeriodNameAttribute(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));
    }

    /**
     * Get the mine area associated with this allocation.
     */
    /** @return BelongsTo<MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class, 'mine_area_id');
    }

    /**
     * Get consumption percentage
     */
    public function getConsumptionPercentageAttribute(): float
    {
        if ($this->allocated_liters == 0) {
            return 0;
        }

        return round(($this->consumed_liters / $this->allocated_liters) * 100, 2);
    }

    /**
     * Get budget spent percentage
     */
    public function getBudgetSpentPercentageAttribute(): float
    {
        if ($this->total_budget_zar == 0) {
            return 0;
        }

        return round(($this->spent_zar / $this->total_budget_zar) * 100, 2);
    }

    /**
     * Check if allocation is exceeded
     */
    public function isExceeded(): bool
    {
        return $this->consumed_liters > $this->allocated_liters;
    }

    /**
     * Check if nearing limit (>80%)
     */
    public function isNearingLimit(): bool
    {
        return $this->consumption_percentage >= 80 && $this->consumption_percentage < 100;
    }

    /**
     * Update consumption from transactions
     */
    public function updateConsumption(): void
    {
        $this->consumed_liters = $this->transactions()
            ->where('transaction_type', 'dispensing')
            ->sum('quantity_liters');

        $this->spent_zar = $this->transactions()
            ->where('transaction_type', 'dispensing')
            ->sum('total_cost');

        $this->remaining_liters = max(0, $this->allocated_liters - $this->consumed_liters);
        $this->remaining_budget_zar = max(0, $this->total_budget_zar - $this->spent_zar);

        // Update status
        if ($this->consumed_liters > $this->allocated_liters) {
            $this->status = 'exceeded';
        } elseif ($this->consumed_liters >= $this->allocated_liters * 0.95) {
            $this->status = 'active';
        }

        $this->save();
    }
}
