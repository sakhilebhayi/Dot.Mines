<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property string|null $status
 * @property array<string, mixed>|null $metadata
 * @property string|null $file_type
 * @property int|null $file_size
 * @property string|null $file_path
 * @property Carbon|null $expiry_date
 * @property Carbon|null $effective_date
 */
class MinePlanUpload extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'mine_area_id',
        'uploaded_by',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'version',
        'status',
        'effective_date',
        'expiry_date',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_size' => 'integer',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Build a temporary signed download URL for this mine plan.
     *
     * @param  \DateTimeInterface|int|null  $expires
     */
    public function signedDownloadUrl($expires = null): string
    {
        $expires = $expires ?? now()->addHours(24);
        $disk = data_get($this->metadata, 'disk', config('filesystems.default'));

        return URL::temporarySignedRoute(
            'mineplans.signed-download',
            $expires,
            [
                'minePlan' => $this->id,
                'disk' => $disk,
                'path' => $this->file_path,
            ]
        );
    }

    /** @return BelongsTo<MineArea,$this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return BelongsTo<User,$this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Get human-readable file size
     */
    protected function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Check if file is an image type
     */
    protected function getIsImageAttribute(): bool
    {
        return in_array($this->file_type, ['image', 'png', 'jpg', 'jpeg', 'gif']);
    }

    /**
     * Check if plan is currently effective
     */
    protected function getIsEffectiveAttribute(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->effective_date && $this->effective_date->isFuture()) {
            return false;
        }
        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return false;
        }

        return true;
    }
}
