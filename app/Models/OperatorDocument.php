<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A file backing an operator's record.
 *
 * The path is never exposed: downloads go through the audited controller
 * route, and medical-kind documents additionally require the medical
 * permission (OperatorDocument::isMedical()).
 *
 * @property int $id
 * @property int $team_id
 * @property int $operator_id
 * @property string $kind
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property-read Operator|null $operator
 */
class OperatorDocument extends Model
{
    use HasTeamFilters, SoftDeletes;

    public const KIND_LICENCE = 'licence';

    public const KIND_MEDICAL = 'medical';

    public const KIND_TRAINING = 'training';

    public const KIND_IDENTIFICATION = 'identification';

    public const KIND_EMPLOYMENT = 'employment';

    public const KIND_COMPETENCY = 'competency';

    public const KIND_OTHER = 'other';

    /** @var array<string, string> */
    public const KINDS = [
        self::KIND_LICENCE => 'Licence',
        self::KIND_MEDICAL => 'Medical Certificate',
        self::KIND_TRAINING => 'Training Certificate',
        self::KIND_IDENTIFICATION => 'Identification',
        self::KIND_EMPLOYMENT => 'Employment Document',
        self::KIND_COMPETENCY => 'Competency Record',
        self::KIND_OTHER => 'Other',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'operator_id',
        'kind',
        'title',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /**
     * The path and disk stay server-side; a serialised document must never
     * hand a client the storage location.
     *
     * @var array<int, string>
     */
    protected $hidden = ['disk', 'path'];

    /**
     * @return BelongsTo<Operator,$this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return BelongsTo<User,$this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Medical documents ride the medical permission, not the general one.
     */
    public function isMedical(): bool
    {
        return $this->kind === self::KIND_MEDICAL;
    }
}
