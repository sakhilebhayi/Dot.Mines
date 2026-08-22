<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription Model
 *
 * Represents a team's subscription to a plan
 * Tracks billing cycle, status, and Paystack integration
 *
 * @property int $id
 * @property int $team_id
 * @property int $subscription_plan_id
 * @property string|null $paystack_subscription_code
 * @property string|null $paystack_customer_code
 * @property string|null $paystack_email_token
 * @property string $status
 * @property string $billing_cycle
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $ends_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Subscription extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'subscription_plan_id',
        'paystack_subscription_code',
        'paystack_customer_code',
        'paystack_email_token',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ends_at',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns the subscription.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the plan for this subscription.
     *
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get payments for this subscription.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get invoices for this subscription.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if subscription is on trial
     */
    public function onTrial(): bool
    {
        return $this->status === 'trial' &&
               $this->trial_ends_at &&
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active']);
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Check if subscription has expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Check if subscription is past due
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Get days remaining in trial
     */
    public function trialDaysRemaining(): int
    {
        if (! $this->onTrial()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Get days remaining in current period
     */
    public function daysRemainingInPeriod(): int
    {
        if (! $this->current_period_end) {
            return 0;
        }

        return max(0, now()->diffInDays($this->current_period_end, false));
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'trial' => 'blue',
            'active' => 'green',
            'past_due' => 'yellow',
            'canceled' => 'red',
            'expired' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get formatted status text
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'trial' => 'Trial',
            'active' => 'Active',
            'past_due' => 'Past Due',
            'canceled' => 'Canceled',
            'expired' => 'Expired',
            default => 'Unknown',
        };
    }

    /**
     * Scope query to active subscriptions
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trial', 'active']);
    }

    /**
     * Scope query to trial subscriptions
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOnTrial($query)
    {
        return $query->where('status', 'trial')
            ->where('trial_ends_at', '>', now());
    }
}
