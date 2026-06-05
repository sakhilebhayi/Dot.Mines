<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription Plan Model
 *
 * Represents available subscription tiers (Basic, Pro, Enterprise)
 * Defines features and limits for each plan
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $price
 * @property float|null $yearly_price
 * @property string|null $paystack_plan_code
 * @property string|null $paystack_yearly_plan_code
 * @property array<string, mixed>|null $features
 * @property int|null $max_machines
 * @property int|null $max_users
 * @property int|null $max_geofences
 * @property int|null $max_mine_areas
 * @property bool $has_advanced_analytics
 * @property bool $has_api_access
 * @property bool $has_priority_support
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'yearly_price',
        'paystack_plan_code',
        'paystack_yearly_plan_code',
        'features',
        'max_machines',
        'max_users',
        'max_geofences',
        'max_mine_areas',
        'has_advanced_analytics',
        'has_api_access',
        'has_priority_support',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'yearly_price' => 'float',
            'features' => 'array',
            'max_machines' => 'integer',
            'max_users' => 'integer',
            'max_geofences' => 'integer',
            'max_mine_areas' => 'integer',
            'has_advanced_analytics' => 'boolean',
            'has_api_access' => 'boolean',
            'has_priority_support' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get subscriptions for this plan.
     */
    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Calculate yearly savings percentage
     */
    public function getYearlySavingsPercentageAttribute(): int
    {
        if (! $this->yearly_price || $this->price <= 0) {
            return 0;
        }

        $monthlyTotal = $this->price * 12;
        $savings = $monthlyTotal - $this->yearly_price;

        return (int) round(($savings / $monthlyTotal) * 100);
    }

    /**
     * Get monthly price for display
     */
    public function getMonthlyPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }

    /**
     * Get yearly price for display
     */
    public function getYearlyPriceDisplayAttribute(): string
    {
        return $this->yearly_price ? number_format($this->yearly_price, 2) : '0.00';
    }

    /**
     * Scope query to active plans only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Check if plan has specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
