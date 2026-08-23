<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

/**
 * Webhooks are gated on the integrations permissions rather than new ones.
 *
 * An outbound webhook is an integration: same people, same job, same risk
 * profile as adding a manufacturer API connection. A separate permission
 * would have to be granted to every existing role to be useful, and would
 * quietly leave everyone unable to manage webhooks until someone noticed.
 */
class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_integrations');
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->current_team_id === $endpoint->team_id
            && $user->hasPermission('view_integrations');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_integrations');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->current_team_id === $endpoint->team_id
            && $user->hasPermission('manage_integrations');
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->update($user, $endpoint);
    }
}
