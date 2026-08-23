<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Model
 *
 * Represents a payment transaction
 * Tracks Paystack payment references and status
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
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Payment extends Model
{
    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns the payment.
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the subscription for this payment.
     *
     * @return BelongsTo<Subscription,$this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the invoice for this payment.
     *
     * @return BelongsTo<Invoice,$this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get formatted amount
     */
    protected function getFormattedAmountAttribute(): string
    {
        return 'R'.number_format($this->amount, 2);
    }

    /**
     * Get status color
     */
    protected function getStatusColorAttribute(): string
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
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSucceeded($query)
    {
        return $query->where('status', 'succeeded');
    }

    /**
     * Scope query to failed payments
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
