<?php

namespace App\Models;

use App\Models\Concerns\ExpiresOn;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A licence or qualification an operator holds.
 *
 * `equipment_type` (App\Support\EquipmentType vocabulary) is what turns this
 * from a record into an authorisation: it names what the holder may operate,
 * so eligibility checks match licences to machines instead of trusting that
 * "has some licence" means "may drive this".
 *
 * @property int $id
 * @property int $team_id
 * @property int $operator_id
 * @property string $title
 * @property string|null $licence_number
 * @property string|null $equipment_category
 * @property string|null $equipment_type
 * @property string|null $issuing_authority
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string $standing
 * @property string|null $notes
 */
class OperatorQualification extends Model
{
    use ExpiresOn, HasTeamFilters, SoftDeletes;

    public const STANDING_ACTIVE = 'active';

    public const STANDING_SUSPENDED = 'suspended';

    public const STANDING_REVOKED = 'revoked';

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'operator_id',
        'title',
        'licence_number',
        'equipment_category',
        'equipment_type',
        'issuing_authority',
        'issued_on',
        'expires_on',
        'standing',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
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
     * A suspended or revoked licence authorises nothing, whatever its date.
     */
    public function isInGoodStanding(): bool
    {
        return $this->standing === self::STANDING_ACTIVE;
    }

    /**
     * Whether this qualification authorises operating the given equipment.
     */
    public function authorises(string $equipmentType): bool
    {
        return $this->equipment_type === $equipmentType && $this->isCurrent();
    }
}
