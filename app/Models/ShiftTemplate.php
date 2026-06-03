<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ShiftTemplate Model
 *
 * @property int $id
 * @property int $team_id
 * @property string $category
 * @property string $title
 * @property string $template_body
 * @property array|null $required_fields
 * @property int $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\User $creator
 */
class ShiftTemplate extends Model
{
    use HasTeamFilters;

    protected $fillable = [
        'team_id',
        'category',
        'title',
        'template_body',
        'required_fields',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_fields' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
