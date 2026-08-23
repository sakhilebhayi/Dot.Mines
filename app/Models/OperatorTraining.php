<?php

namespace App\Models;

use App\Models\Concerns\ExpiresOn;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A training course or competency assessment an operator completed.
 *
 * @property int $id
 * @property int $team_id
 * @property int $operator_id
 * @property string $course
 * @property string|null $category
 * @property string|null $equipment_type
 * @property string|null $provider
 * @property string|null $certificate_number
 * @property Carbon|null $completed_on
 * @property Carbon|null $expires_on
 * @property string $competency
 * @property string|null $notes
 */
class OperatorTraining extends Model
{
    use ExpiresOn, HasTeamFilters, SoftDeletes;

    public const COMPETENT = 'competent';

    public const IN_PROGRESS = 'in_progress';

    public const FAILED = 'failed';

    public const CATEGORY_SITE_INDUCTION = 'site_induction';

    public const CATEGORY_SAFETY = 'safety';

    public const CATEGORY_MACHINE_COMPETENCY = 'machine_competency';

    public const CATEGORY_EMERGENCY = 'emergency';

    public const CATEGORY_REFRESHER = 'refresher';

    /** @var array<string, string> */
    public const CATEGORIES = [
        self::CATEGORY_SITE_INDUCTION => 'Site Induction',
        self::CATEGORY_SAFETY => 'Safety Training',
        self::CATEGORY_MACHINE_COMPETENCY => 'Machine Competency',
        self::CATEGORY_EMERGENCY => 'Emergency Training',
        self::CATEGORY_REFRESHER => 'Refresher Training',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'operator_id',
        'course',
        'category',
        'equipment_type',
        'provider',
        'certificate_number',
        'completed_on',
        'expires_on',
        'competency',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Operator,$this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * Training in progress or failed does not count, whatever its dates say.
     */
    public function isInGoodStanding(): bool
    {
        return $this->competency === self::COMPETENT;
    }
}
