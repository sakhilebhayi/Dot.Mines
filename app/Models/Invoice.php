<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice Model
 *
 * Represents a billing invoice
 * Tracks line items and payment status
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $subscription_id
 * @property int|null $payment_id
 * @property string $invoice_number
 * @property string|null $paystack_invoice_code
 * @property float $subtotal
 * @property float $tax
 * @property float $total
 * @property string $currency
 * @property string $status
 * @property \Carbon\Carbon $issued_at
 * @property \Carbon\Carbon|null $due_at
 * @property \Carbon\Carbon|null $paid_at
 * @property string|null $pdf_url
 * @property array<string, mixed>|null $line_items
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice orderBy(string $column, string $direction = 'asc')
 * @method static Invoice|null find(mixed $id, array $columns = ['*'])
 * @method static Invoice findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'subscription_id',
        'payment_id',
        'invoice_number',
        'paystack_invoice_code',
        'subtotal',
        'tax',
        'total',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'pdf_url',
        'line_items',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'tax' => 'float',
            'total' => 'float',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'line_items' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team that owns the invoice.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the subscription for this invoice.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the payment for this invoice.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'R'.number_format($this->total, 2);
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'green',
            'open' => 'yellow',
            'draft' => 'blue',
            'void' => 'red',
            'uncollectible' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'open' &&
               $this->due_at &&
               $this->due_at->isPast();
    }

    /**
     * Scope query to paid invoices
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope query to unpaid invoices
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
