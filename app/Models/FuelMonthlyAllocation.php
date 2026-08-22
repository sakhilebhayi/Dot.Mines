<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FuelMonthlyAllocation Model
 */
/**
 * @property int $id
 * @property int $team_id
 * @property int|null $mine_area_id
 * @property numeric-string|float $remaining_liters
 * @property numeric-string|float|null $fuel_price_per_liter
 * @property float|numeric-string|null $allocated_liters
 * @property float|numeric-string|null $consumed_liters
 * @property float|numeric-string|null $total_budget_zar
 * @property float|numeric-string|null $spent_zar
 * @property string|null $status
 * @property int|null $month
 * @property int|null $year
 * @property-read float $consumption_percentage
 * @property float|numeric-string $remaining_budget_zar
 */
class FuelMonthlyAllocation extends Model
{
    use HasTeamFilters;

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
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

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<FuelTransaction,$this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class, 'monthly_allocation_id');
    }

    /**
     * Get the period name (e.g., "January 2026")
     */
    protected function getPeriodNameAttribute(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year) ?: null);
    }

    /**
     * Get the mine area associated with this allocation.
     *
     * @return BelongsTo<MineArea,$this>
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class, 'mine_area_id');
    }

    /**
     * Get consumption percentage
     */
    protected function getConsumptionPercentageAttribute(): float
    {
        if ($this->allocated_liters == 0) {
            return 0;
        }

        return round(($this->consumed_liters / $this->allocated_liters) * 100, 2);
    }

    /**
     * Get budget spent percentage
     */
    protected function getBudgetSpentPercentageAttribute(): float
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
