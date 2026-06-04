<?php

namespace App\Models;

use App\Mail\ReportReadyMail;
use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Report Model
 *
 * Stores generated reports with configuration and file storage
 *
 * @property int $id
 * @property int $team_id
 * @property string $title
 * @property string $type
 * @property string $status
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $format
 * @property array<string, mixed>|null $filters
 * @property int|string|null $generated_by
 * @property \Carbon\Carbon|null $generated_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Report where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Report whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Report orderBy(string $column, string $direction = 'asc')
 * @method static Report|null find(mixed $id, array $columns = ['*'])
 * @method static Report findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class Report extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'title',
        'type', // truck_sensors, tire_condition, load_cycle, fuel, engine_parts, maintenance, custom
        'status', // pending, completed, failed
        'file_path',
        'file_size',
        'format', // pdf, csv, xlsx
        'filters', // JSON with report filters
        'generated_by',
        'generated_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'json',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team this report belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who generated this report
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Check if report is still available
     */
    public function isAvailable(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Mark report as processing
     */
    public function markProcessing(): static
    {
        $this->update([
            'status' => 'processing',
        ]);

        return $this;
    }

    /**
     * Mark report as completed
     */
    public function markCompleted(string $filePath, ?int $fileSize = null): static
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'generated_at' => now(),
        ]);

        // Send report-ready emails to team users (fallback: all team users)
        try {
            if ($this->team) {
                $emails = $this->team->users()->pluck('email')->filter()->unique()->toArray();
                if (! empty($emails)) {
                    foreach (array_chunk($emails, 50) as $batch) {
                        Mail::to($batch)->queue(new ReportReadyMail($this));
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send report-ready emails', ['report_id' => $this->id, 'error' => $e->getMessage()]);
        }

        return $this;
    }

    /**
     * Mark report as failed
     */
    public function markFailed(?string $reason = null): bool
    {
        if ($reason) {
            Log::warning('Report generation failed', [
                'report_id' => $this->id,
                'reason' => $reason,
            ]);
        }

        return $this->update([
            'status' => 'failed',
        ]);
    }
}
