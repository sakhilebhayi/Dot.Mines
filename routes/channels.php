<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Feed channel — scoped to team (mine)
 * Only members of that team may subscribe.
 */
Broadcast::channel('feed.team.{teamId}', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Machine location updates — team-scoped.
 */
Broadcast::channel('machines.team.{teamId}', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Alert events — team-scoped.
 */
Broadcast::channel('alerts.team.{teamId}', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Geofence crossing events — team-scoped.
 */
Broadcast::channel('geofences.team.{teamId}', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Production dashboard real-time updates — team-scoped.
 */
Broadcast::channel('production.team.{teamId}', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Maintenance updates — team-scoped.
 */
Broadcast::channel('maintenance.team.{teamId}', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * AI notifications — authenticated user owns the channel.
 */
Broadcast::channel('ai.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Team notification bell — real-time notification count updates.
 */
Broadcast::channel('team.{teamId}.notifications', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Team sensor readings and status changes.
 */
Broadcast::channel('team.{teamId}.sensors', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});

/**
 * Team alert channel (machine offline, compliance violations).
 */
Broadcast::channel('team.{teamId}.alerts', function ($user, $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});
