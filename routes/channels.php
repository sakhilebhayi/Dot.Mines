<?php

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

/*
 * Team firehose (hybrid Slice 3, brief §13): every domain event the app
 * broadcasts targets private-team.{teamId}. Membership is the only key --
 * authorization derives from the authenticated user, never from anything
 * the client claims (brief §9).
 */
Broadcast::channel('team.{teamId}', function (User $user, int $teamId) {
    $team = Team::query()->find($teamId);

    return $team !== null && $user->belongsToTeam($team);
});

/*
 * Per-machine channel (machine-detail pages). Global scopes are bypassed
 * deliberately: the machine's own team is the authority, and membership in
 * THAT team is what's checked.
 */
Broadcast::channel('machine.{machineId}', function (User $user, int $machineId) {
    $machine = Machine::query()->withoutGlobalScopes()->find($machineId);

    if ($machine === null) {
        return false;
    }

    $team = Team::query()->find($machine->team_id);

    return $team !== null && $user->belongsToTeam($team);
});
