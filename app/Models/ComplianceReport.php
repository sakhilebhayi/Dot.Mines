<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mine_area_id
 * @property string $report_type
 * @property int|null $generated_by
 * @property Carbon|null $report_date
 * @property string $status
 * @property array<string, mixed>|null $data
 * @property string|null $file_path
 * @property float|null $compliance_score
 * @property array<string, mixed>|null $issues
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MineArea|null $mineArea
 * @property-read User|null $generator
 *
 * @psalm-suppress UnusedClass -- schema holder for the compliance_reports table; UI consumer planned
 */
class ComplianceReport extends Model
{
    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'data' => 'json',
        'issues' => 'json',
        'report_date' => 'date',
        'compliance_score' => 'float',
    ];

    /** @return BelongsTo<MineArea,$this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return BelongsTo<User,$this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
