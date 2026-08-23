<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person who operates the fleet.
 *
 * Not a User. A user signs in to Dot.Mines; an operator drives an ADT, and
 * most of them never do the first thing. `user_id` links the two only where
 * someone is both.
 *
 * This model deliberately stays thin: qualifications, medicals and training
 * each own their own table and their own rules, so that adding a credential
 * type later does not mean widening this class.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property string|null $department
 * @property string|null $job_title
 * @property string|null $employment_type
 * @property Carbon|null $employed_from
 * @property Carbon|null $employed_until
 * @property int|null $supervisor_id
 * @property int|null $mine_area_id
 * @property string|null $default_shift
 * @property string $employment_status
 * @property string|null $photo_path
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, OperatorQualification> $qualifications
 * @property-read Collection<int, OperatorMedical> $medicals
 * @property-read Collection<int, OperatorTraining> $trainings
 */
class Operator extends Model
{
    /** @use HasFactory<OperatorFactory> */
    use HasFactory, HasTeamFilters, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_TRAINING = 'training';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Employment states. Deliberately short: these are the states an employer
     * sets. Whether someone is available, on shift or blocked by an expired
     * licence is worked out from assignments and credentials, because a
     * status column that can disagree with the data behind it is worse than
     * no column.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_LEAVE => 'On Leave',
        self::STATUS_TRAINING => 'In Training',
        self::STATUS_SUSPENDED => 'Suspended',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'user_id',
        'employee_number',
        'first_name',
        'last_name',
        'preferred_name',
        'email',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'department',
        'job_title',
        'employment_type',
        'employed_from',
        'employed_until',
        'supervisor_id',
        'mine_area_id',
        'default_shift',
        'employment_status',
        'photo_path',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'employed_from' => 'date',
            'employed_until' => 'date',
        ];
    }

    protected function getNameAttribute(): string
    {
        $first = $this->preferred_name !== null && $this->preferred_name !== ''
            ? $this->preferred_name
            : $this->first_name;

        return trim($first.' '.$this->last_name);
    }

    /**
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Operator,$this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'supervisor_id');
    }

    /**
     * @return BelongsTo<MineArea,$this>
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * @return HasMany<OperatorQualification,$this>
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(OperatorQualification::class);
    }

    /**
     * @return HasMany<OperatorMedical,$this>
     */
    public function medicals(): HasMany
    {
        return $this->hasMany(OperatorMedical::class);
    }

    /**
     * @return HasMany<OperatorTraining,$this>
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(OperatorTraining::class);
    }

    /**
     * The medical that decides fitness today: the one that expires last.
     *
     * Operators accumulate medicals over the years and an older certificate
     * must never be able to answer "are they fit?" just because it was loaded
     * first.
     */
    public function currentMedical(): ?OperatorMedical
    {
        $medicals = $this->relationLoaded('medicals')
            ? $this->medicals
            : $this->medicals()->get();

        return $medicals
            ->sortByDesc(fn (OperatorMedical $medical): string => $medical->expires_on?->toDateString() ?? '0000-00-00')
            ->first();
    }

    /**
     * Whether the employment record itself permits work today -- before any
     * question of licences or medicals.
     */
    public function isEmployed(): bool
    {
        if ($this->employment_status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->employed_until !== null && $this->employed_until->isBefore(now()->startOfDay())) {
            return false;
        }

        return true;
    }
}
