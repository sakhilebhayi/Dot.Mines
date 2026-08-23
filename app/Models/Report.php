<?php

namespace App\Models;

use App\Mail\ReportReadyMail;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
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
 * @property string $format
 * @property array<string, mixed>|null $filters
 * @property int|string|null $generated_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Team|null $team
 * @property string|null $error_message
 */
class Report extends Model
{
    use HasTeamFilters;

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'title',
        'type', // truck_sensors, tire_condition, load_cycle, fuel, engine_parts, maintenance, custom
        'status', // pending, completed, failed
        'error_message',
        'file_path',
        'file_size',
        'format', // pdf, csv, xlsx
        'filters', // JSON with report filters
        'generated_by',
        'generated_at',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'filters' => 'json',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team this report belongs to
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who generated this report
     */
    /** @return BelongsTo<User,$this> */
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

        // Send report-ready emails to everyone on the team. team->users() is
        // Jetstream's pivot-only relation, which excludes the owner unless
        // they were separately attached as a member -- for a solo team (the
        // common case for a brand-new team) that silently emailed nobody.
        // allUsers() merges the pivot members with the owner.
        try {
            if ($this->team) {
                // "Email Reports" was a real, saved preference that nothing
                // ever read -- every team member got this regardless.
                /** @var Collection<int, User> $members */
                $members = $this->team->allUsers();
                $emails = $members
                    ->filter(fn (User $user): bool => $user->wantsEmailReports())
                    ->pluck('email')->filter()->unique()->toArray();
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
    public function markFailed(?string $errorMessage = null): bool
    {
        return $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
