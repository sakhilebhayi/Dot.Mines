<?php

namespace App\Models;

use Laravel\Jetstream\Membership as JetstreamMembership;

/** @psalm-suppress UnusedClass -- registered via Jetstream::useMembershipModel() / config strings */
class Membership extends JetstreamMembership
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
}
