<?php

namespace App\Models;

use App\Models\Concerns\ExpiresOn;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An occupational medical certificate.
 *
 * This is health information about an identified person. Access is gated by
 * OperatorPolicy::viewMedical / manageMedical -- nothing renders or returns a
 * medical row without passing one of those, and the fields most people need
 * (is there a current medical, when does it lapse) are exposed through the
 * operator's compliance summary without the detail.
 *
 * @property int $id
 * @property int $team_id
 * @property int $operator_id
 * @property string|null $certificate_number
 * @property string|null $provider
 * @property Carbon|null $examined_on
 * @property Carbon|null $expires_on
 * @property string $fitness
 * @property bool $has_restrictions
 * @property string|null $restrictions
 * @property string|null $notes
 */
class OperatorMedical extends Model
{
    use ExpiresOn, HasTeamFilters, SoftDeletes;

    public const FIT = 'fit';

    public const FIT_WITH_RESTRICTIONS = 'fit_with_restrictions';

    public const TEMPORARILY_UNFIT = 'temporarily_unfit';

    public const UNFIT = 'unfit';

    /** @var array<string, string> */
    public const FITNESS_LABELS = [
        self::FIT => 'Fit for Duty',
        self::FIT_WITH_RESTRICTIONS => 'Fit with Restrictions',
        self::TEMPORARILY_UNFIT => 'Temporarily Unfit',
        self::UNFIT => 'Unfit for Duty',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'operator_id',
        'certificate_number',
        'provider',
        'examined_on',
        'expires_on',
        'fitness',
        'has_restrictions',
        'restrictions',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'examined_on' => 'date',
            'expires_on' => 'date',
            'has_restrictions' => 'boolean',
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
     * An unfit finding invalidates the certificate for work regardless of
     * date. Restrictions do NOT: fit-with-restrictions still clears the
     * medical gate, and the restrictions themselves are surfaced for a human
     * decision rather than machine-blocked, because free-text like "no night
     * shift" is not something the code should pretend to interpret.
     */
    public function isInGoodStanding(): bool
    {
        return in_array($this->fitness, [self::FIT, self::FIT_WITH_RESTRICTIONS], true);
    }
}
