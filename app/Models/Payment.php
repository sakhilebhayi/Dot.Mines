<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Model
 *
 * Represents a payment transaction
 * Tracks Stripe payment intents and status
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $subscription_id
 * @property string|null $paystack_reference
 * @property string|null $paystack_invoice_id
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $description
 * @property string|null $failure_reason
 * @property \Carbon\Carbon|null $paid_at
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Payment where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment orderBy(string $column, string $direction = 'asc')
 * @method static Payment|null find(mixed $id, array $columns = ['*'])
 * @method static Payment findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'subscription_id',
        'paystack_reference',
        'paystack_invoice_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'description',
        'failure_reason',
        'paid_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team that owns the payment.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the subscription for this payment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the invoice for this payment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R'.number_format($this->amount, 2);
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'succeeded' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Scope query to successful payments
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', 'succeeded');
    }

    /**
     * Scope query to failed payments
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }
}
