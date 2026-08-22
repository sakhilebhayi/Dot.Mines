<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

/** @psalm-suppress UnusedClass -- registered via Jetstream::useTeamInvitationModel() / config strings */
class TeamInvitation extends JetstreamTeamInvitation
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'role',
    ];

    /**
     * Get the team that the invitation belongs to.
     */
    /** @return BelongsTo<Team,$this> */
    #[\Override]
    public function team(): BelongsTo
    {
        /** @var class-string<Team> $teamModel */
        $teamModel = Jetstream::teamModel();

        return $this->belongsTo($teamModel);
    }
}
