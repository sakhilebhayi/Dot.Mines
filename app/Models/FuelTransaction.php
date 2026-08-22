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
 */
class FuelTransaction extends Model
{
    /** @use HasFactory<FuelTransactionFactory> */
    use HasFactory, HasTeamFilters;

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
     * @property string|float $quantity_liters
     * @property string|float $unit_price
     * @property string|float $total_cost
     * @property string|null $fuel_type
     * @property Carbon $transaction_date
     * @property string|float|null $odometer_reading
     * @property string|float|null $machine_hours
     * @property string|null $supplier
     * @property string|null $invoice_number
     * @property string|null $receipt_file_path
     * @property int|null $from_tank_id
     * @property int|null $to_tank_id
     * @property string|null $notes
     * @property float|null $cost_per_liter
     * @property Carbon $created_at
     * @property Carbon $updated_at
     *
     * @method static \Illuminate\Database\Eloquent\Builder|FuelTransaction where(string $column, mixed $operator = null, mixed $value = null)
     * @method static \Illuminate\Database\Eloquent\Builder|FuelTransaction whereIn(string $column, array $values)
     * @method static \Illuminate\Database\Eloquent\Builder|FuelTransaction orderBy(string $column, string $direction = 'asc')
     * @method static FuelTransaction|null find(mixed $id, array $columns = ['*'])
     * @method static FuelTransaction findOrFail(mixed $id, array $columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
     */
    /** @var list<string> */
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
