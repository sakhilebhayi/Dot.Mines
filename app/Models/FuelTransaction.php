<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FuelTransaction Model
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $monthly_allocation_id
 * @property int|null $fuel_tank_id
 * @property int|null $machine_id
 * @property int|null $user_id
 * @property string $transaction_type
 * @property float $quantity_liters
 * @property float $unit_price
 * @property float $total_cost
 * @property string|null $fuel_type
 * @property Carbon $transaction_date
 * @property float|null $odometer_reading
 * @property float|null $machine_hours
 * @property string|null $supplier
 * @property string|null $invoice_number
 * @property string|null $receipt_file_path
 * @property int|null $from_tank_id
 * @property int|null $to_tank_id
 * @property string|null $notes
 * @property float|null $cost_per_liter
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FuelTank|null $fuelTank
 * @property-read Machine|null $machine
 * @property-read FuelMonthlyAllocation|null $monthlyAllocation
 * @property-read User|null $user
 */
class FuelTransaction extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'monthly_allocation_id',
        'fuel_tank_id',
        'machine_id',
        'user_id',
        'transaction_type',
        'quantity_liters',
        'unit_price',
        'total_cost',
        'fuel_type',
        'transaction_date',
        'odometer_reading',
        'machine_hours',
        'supplier',
        'invoice_number',
        'receipt_file_path',
        'from_tank_id',
        'to_tank_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_liters' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'odometer_reading' => 'decimal:2',
            'machine_hours' => 'decimal:2',
            'transaction_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<FuelTank, $this> */
    public function fuelTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FuelTank, $this> */
    public function fromTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'from_tank_id');
    }

    /** @return BelongsTo<FuelTank, $this> */
    public function toTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'to_tank_id');
    }

    /**
     * Get transaction cost per liter
     */
    public function getCostPerLiterAttribute(): ?float
    {
        if ($this->quantity_liters == 0 || ! $this->total_cost) {
            return null;
        }

        return round($this->total_cost / $this->quantity_liters, 2);
    }

    /**
     * Scope for specific transaction type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope for specific fuel type
     */
    public function scopeFuelType(Builder $query, string $type): Builder
    {
        return $query->where('fuel_type', $type);
    }
}
