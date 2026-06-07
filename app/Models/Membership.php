<?php

namespace App\Models;

use Laravel\Jetstream\Membership as JetstreamMembership;

class Membership extends JetstreamMembership
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are not mass assignable.
     * Only the primary key is guarded; role and team_id are set by
     * Jetstream's team management actions which are already authorized.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];
}
