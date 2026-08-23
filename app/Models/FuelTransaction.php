<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\FuelTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property float|numeric-string|null $quantity_liters
 * @property float|numeric-string|null $total_cost
 * @property int $id
 * @property int $team_id
 * @property int|null $fuel_tank_id
 * @property int|null $machine_id
 * @property int|null $from_tank_id
 * @property int|null $to_tank_id
 * @property string $transaction_type
 * @property string $currency
 * @property string|null $receipt_file_path
 * @property float|numeric-string|null $unit_price
 * @property Carbon|null $transaction_date
 * @property-read FuelTank|null $fuelTank
 * @property-read Machine|null $machine
 * @property int $user_id
 * @property string $fuel_type
 * @property float|numeric-string|null $odometer_reading
 * @property float|numeric-string|null $machine_hours
 * @property string|null $supplier
 * @property string|null $invoice_number
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FuelTransaction extends Model
{
    /** @use HasFactory<FuelTransactionFactory> */
    use HasFactory, HasTeamFilters;

    /** @var array<int, string> */
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
        'currency',
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

    /** @var array<string, string> */
    protected $casts = [
        'quantity_liters' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'odometer_reading' => 'decimal:2',
        'machine_hours' => 'decimal:2',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<FuelTank,$this> */
    public function fuelTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class);
    }

    /** @return BelongsTo<Machine,$this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FuelTank,$this> */
    public function fromTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'from_tank_id');
    }

    /** @return BelongsTo<FuelTank,$this> */
    public function toTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'to_tank_id');
    }

    /**
     * Get transaction cost per liter
     */
    protected function getCostPerLiterAttribute(): ?float
    {
        $quantity = (float) $this->quantity_liters;
        $cost = (float) ($this->total_cost ?? 0);

        if ($quantity === 0.0 || $cost === 0.0) {
            return null;
        }

        return round($cost / $quantity, 2);
    }

    /**
     * Scope for specific transaction type
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope for date range
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDateRange($query, string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate)
    {
        $query->whereBetween('transaction_date', [$startDate, $endDate]);

        return $query;
    }

    /**
     * Scope for specific fuel type
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFuelType($query, string $type)
    {
        return $query->where('fuel_type', $type);
    }
}
