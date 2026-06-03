<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
