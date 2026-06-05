<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ComplianceReport Model
 *
 * @property int $id
 * @property int|null $mine_area_id
 * @property string $report_type
 * @property int|null $generated_by
 * @property Carbon $report_date
 * @property string $status
 * @property array<string, mixed>|null $data
 * @property string|null $file_path
 * @property float|null $compliance_score
 * @property array<int, mixed>|null $issues
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MineArea $mineArea
 */
class ComplianceReport extends Model
{
    protected $fillable = [
        'mine_area_id',
        'report_type',
        'generated_by',
        'report_date',
        'status',
        'data',
        'file_path',
        'compliance_score',
        'issues',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'json',
            'issues' => 'json',
            'report_date' => 'date',
            'compliance_score' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return BelongsTo<MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }
}
